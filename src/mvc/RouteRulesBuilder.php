<?php

namespace mgboot\core\mvc;

use Lcobucci\JWT\Token;
use mgboot\Cast;
use mgboot\core\annotation\ClientIp;
use mgboot\core\annotation\DeleteMapping;
use mgboot\core\annotation\DtoBind;
use mgboot\core\annotation\GetMapping;
use mgboot\core\annotation\HttpHeader;
use mgboot\core\annotation\JwtAuth;
use mgboot\core\annotation\JwtClaim;
use mgboot\core\annotation\ParamMap;
use mgboot\core\annotation\PatchMapping;
use mgboot\core\annotation\PathVariable;
use mgboot\core\annotation\PostMapping;
use mgboot\core\annotation\PutMapping;
use mgboot\core\annotation\RequestBody;
use mgboot\core\annotation\RequestMapping;
use mgboot\core\annotation\RequestParam;
use mgboot\core\annotation\UploadedFile;
use mgboot\core\annotation\Validate;
use mgboot\core\http\server\Request;
use mgboot\core\MgBoot;
use mgboot\util\FileUtils;
use mgboot\util\ReflectUtils;
use mgboot\util\StringUtils;
use mgboot\util\TokenizeUtils;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Stringable;
use Throwable;

final class RouteRulesBuilder
{
    private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * @return RouteRule[]
     */
    public static function buildRouteRules(): array
    {
        $dir = MgBoot::getControllerDir();

        if ($dir === '' || !is_dir($dir)) {
            return [];
        }

        $files = [];
        FileUtils::scanFiles($dir, $files);
        $rules = [];

        foreach ($files as $fpath) {
            if (!preg_match('/\.php$/', $fpath)) {
                continue;
            }

            try {
                $tokens = token_get_all(file_get_contents($fpath));
                $className = TokenizeUtils::getQualifiedClassName($tokens);
                $clazz = new ReflectionClass($className);
            } catch (Throwable) {
                $className = '';
                $clazz = null;
            }

            if (empty($className) || !($clazz instanceof ReflectionClass)) {
                continue;
            }

            $anno1 = ReflectUtils::getClassAnnotation($clazz, RequestMapping::class);

            try {
                $methods = $clazz->getMethods(ReflectionMethod::IS_PUBLIC);
            } catch (Throwable) {
                $methods = [];
            }

            foreach ($methods as $method) {
                try {
                    $map1 = array_merge(
                        [
                            'handler' => "$className@{$method->getName()}",
                            'handlerFuncArgs' => self::buildHandlerFuncArgs($method)
                        ],
                        self::buildValidateRules($method),
                        self::buildJwtAuthSettings($method),
                        self::buildExtraAnnotations($method)
                    );
                } catch (Throwable) {
                    continue;
                }

                $rule = self::buildRouteRule(GetMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $rule = self::buildRouteRule(PostMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $rule = self::buildRouteRule(PutMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $rule = self::buildRouteRule(PatchMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $rule = self::buildRouteRule(DeleteMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $items = self::buildRouteRulesForRequestMapping($method, $anno1, $map1);

                if (!empty($items)) {
                    array_push($rules, ...$items);
                }
            }
        }

        return $rules;
    }

    private static function buildRouteRule(
        string $clazz,
        ReflectionMethod $method,
        ?RequestMapping $anno,
        array $data
    ): ?RouteRule
    {
        $httpMethod = match (StringUtils::substringAfterLast($clazz, "\\")) {
            'GetMapping' => 'GET',
            'PostMapping' => 'POST',
            'PutMapping' => 'PUT',
            'PatchMapping' => 'PATCH',
            'DeleteMapping' => 'DELETE',
            default => ''
        };

        if ($httpMethod === '') {
            return null;
        }

        try {
            $newAnno =  ReflectUtils::getMethodAnnotation($method, $clazz);

            if (!is_object($newAnno) || !method_exists($newAnno, 'getValue')) {
                return null;
            }

            $data = array_merge(
                $data,
                self::buildRequestMapping($anno, $newAnno->getValue()),
                compact('httpMethod')
            );

            return RouteRule::create($data);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param ReflectionMethod $method
     * @param RequestMapping|null $anno
     * @param array $data
     * @return RouteRule[]
     */
    private static function buildRouteRulesForRequestMapping(
        ReflectionMethod $method,
        ?RequestMapping $anno,
        array $data
    ): array
    {
        try {
            $newAnno =  ReflectUtils::getMethodAnnotation($method, RequestMapping::class);

            if (!is_object($newAnno) || !method_exists($newAnno, 'getValue')) {
                return [];
            }

            $map1 = self::buildRequestMapping($anno, $newAnno->getValue());

            return [
                RouteRule::create(array_merge($data, $map1, ['httpMethod' => 'GET'])),
                RouteRule::create(array_merge($data, $map1, ['httpMethod' => 'POST']))
            ];
        } catch (Throwable) {
            return [];
        }
    }

    private static function buildRequestMapping(?RequestMapping $anno, string $requestMapping): array
    {
        $requestMapping = preg_replace('/[\x20\t]+/', '', $requestMapping);
        $requestMapping = trim($requestMapping, '/');

        if ($anno instanceof RequestMapping) {
            $s1 = preg_replace('/[\x20\t]+/', '', $anno->getValue());

            if (!empty($s1)) {
                $requestMapping = trim($s1, '/') . '/' . $requestMapping;
            }
        }

        $requestMapping = StringUtils::ensureLeft($requestMapping, '/');
        return compact('requestMapping');
    }

    /**
     * @param ReflectionMethod $method
     * @return HandlerFuncArgInfo[]
     * @noinspection PhpContinueTargetingSwitchInspection
     */
    private static function buildHandlerFuncArgs(ReflectionMethod $method): array
    {
        $params = $method->getParameters();

        foreach ($params as $i => $p) {
            $type = $p->getType();

            if (!($type instanceof ReflectionNamedType)) {
                $params[$i] = HandlerFuncArgInfo::create(['name' => $p->getName()]);
                continue;
            }

            $map1 = [
                'name' => $p->getName(),
                'type' => $type->getName()
            ];

            if ($type->allowsNull()) {
                $map1['nullable'] = true;
            }

            switch ($type->getName()) {
                case Request::class:
                    $map1['request'] = true;
                    $params[$i] = HandlerFuncArgInfo::create($map1);
                    continue;
                case Token::class:
                    $map1['jwt'] = true;
                    $params[$i] = HandlerFuncArgInfo::create($map1);
                    continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, ClientIp::class);

            if ($anno instanceof ClientIp) {
                $map1['clientIp'] = true;
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, HttpHeader::class);

            if ($anno instanceof HttpHeader) {
                $map1['httpHeaderName'] = $anno->getName();
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, JwtClaim::class);

            if ($anno instanceof JwtClaim) {
                $map1['jwtClaimName'] = empty($anno->getName()) ? $p->getName() : $anno->getName();
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, PathVariable::class);

            if ($anno instanceof PathVariable) {
                $map1['pathVariableName'] = empty($anno->getName()) ? $p->getName() : $anno->getName();
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, RequestParam::class);

            if ($anno instanceof RequestParam) {
                $map1['requestParamName'] = empty($anno->getName()) ? $p->getName() : $anno->getName();
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, ParamMap::class);

            if ($anno instanceof ParamMap) {
                $map1['paramMap'] = true;
                $map1['paramMapRules'] = empty($anno->getRules()) ? [] : $anno->getRules();
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, UploadedFile::class);

            if ($anno instanceof UploadedFile) {
                $map1['uploadedFile'] = true;
                $map1['formFieldName'] = empty($anno->getFormFieldName()) ? $p->getName() : $anno->getFormFieldName();
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, RequestBody::class);

            if ($anno instanceof RequestBody) {
                $map1['needRequestBody'] = true;
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno = ReflectUtils::getParameterAnnotation($p, DtoBind::class);

            if ($anno instanceof DtoBind && !$type->isBuiltin()) {
                $map1['dtoClassName'] = StringUtils::ensureLeft($type->getName(), "\\");
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $params[$i] = HandlerFuncArgInfo::create($map1);
        }

        return $params;
    }

    private static function buildJwtAuthSettings(ReflectionMethod $method): array
    {
        $anno =  ReflectUtils::getMethodAnnotation($method, JwtAuth::class);

        if (!($anno instanceof JwtAuth)) {
            return [];
        }

        return ['jwtSettingsKey' => $anno->getSettingsKey()];
    }

    private static function buildValidateRules(ReflectionMethod $method): array
    {
        $anno = ReflectUtils::getMethodAnnotation($method, Validate::class);

        if (!($anno instanceof Validate)) {
            return [];
        }

        return ['validateRules' => $anno->getRules(), 'failfast' => $anno->isFailfast()];
    }

    private static function buildExtraAnnotations(ReflectionMethod $method): array
    {
        try {
            $annos = $method->getAttributes();
        } catch (Throwable) {
            return [];
        }

        $excludes = [
            StringUtils::ensureLeft(DeleteMapping::class, "\\"),
            StringUtils::ensureLeft(GetMapping::class, "\\"),
            StringUtils::ensureLeft(JwtAuth::class, "\\"),
            StringUtils::ensureLeft(PatchMapping::class, "\\"),
            StringUtils::ensureLeft(PostMapping::class, "\\"),
            StringUtils::ensureLeft(PutMapping::class, "\\"),
            StringUtils::ensureLeft(RequestMapping::class, "\\"),
            StringUtils::ensureLeft(Validate::class, "\\")
        ];

        $extraAnnotations = [];

        foreach ($annos as $it) {
            $clazz = StringUtils::ensureLeft($it->getName(), "\\");

            if (in_array($clazz, $excludes)) {
                continue;
            }

            if ($it instanceof Stringable || method_exists($it, '__toString')) {
                $s1 = Cast::toString($it->__toString());

                if (!str_contains($s1, $clazz)) {
                    $s1 = $clazz . $s1;
                }
            } else {
                $s1 = $clazz;
            }

            $extraAnnotations[] = $s1;
        }

        return compact('extraAnnotations');
    }
}
