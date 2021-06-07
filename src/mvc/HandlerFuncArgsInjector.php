<?php

namespace mgboot\core\mvc;

use Lcobucci\JWT\Token;
use mgboot\common\AppConf;
use mgboot\common\ArrayUtils;
use mgboot\common\Cast;
use mgboot\common\JsonUtils;
use mgboot\common\StringUtils;
use mgboot\common\UploadedFile;
use mgboot\core\http\server\Request;
use mgboot\core\MgBoot;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use RuntimeException;
use stdClass;
use Throwable;

final class HandlerFuncArgsInjector
{
    private static string $err1 = 'fail to inject arg for handler function %s, name: %s, type: %s';

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function inject(Request $req): array
    {
        $routeRule = $req->getRouteRule();
        $handler = $routeRule->getHandler();
        $args = [];

        foreach ($routeRule->getHandlerFuncArgs() as $info) {
            if ($info->isRequest()) {
                $args[] = $req;
                continue;
            }

            if ($info->isJwt()) {
                $jwt = $req->getJwt();

                if (!($jwt instanceof Token) && !$info->isNullable()) {
                    throw new RuntimeException(sprintf(self::$err1, $handler, $info->getName(), $info->getType()));
                }

                $args[] = $jwt;
                continue;
            }

            if ($info->isClientIp()) {
                $args[] = $req->getClientIp();
                continue;
            }

            if ($info->getHttpHeaderName() !== '') {
                $args[] = $req->getHeader($info->getHttpHeaderName());
                continue;
            }

            if ($info->getJwtClaimName() !== '') {
                self::injectJwtClaim($req, $args, $info);
                continue;
            }

            if ($info->getPathVariableName() !== '') {
                self::injectPathVariable($req, $args, $info);
                continue;
            }

            if ($info->getRequestParamName() !== '') {
                self::injectRequestParam($req, $args, $info);
                continue;
            }

            if ($info->isParamMap()) {
                self::injectParamMap($req, $args, $info);
                continue;
            }

            if ($info->isUploadedFile()) {
                self::injectUploadedFile($req, $args, $info);
                continue;
            }

            if ($info->isNeedRequestBody()) {
                self::injectRequestBody($req, $args, $info);
                continue;
            }

            if ($info->getDtoClassName() !== '') {
                self::injectDto($req, $args, $info);
                continue;
            }

            throw new RuntimeException(sprintf(self::$err1, $handler, $info->getName(), $info->getType()));
        }

        return $args;
    }

    private static function injectJwtClaim(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $claimName = $info->getJwtClaimName();

        switch ($info->getType()) {
            case 'int':
                $args[] = $req->jwtIntCliam($claimName);
                break;
            case 'float':
                $args[] = $req->jwtFloatClaim($claimName);
                break;
            case 'bool':
                $args[] = $req->jwtBooleanClaim($claimName);
                break;
            case 'string':
                $args[] = $req->jwtStringClaim($claimName);
                break;
            case 'array':
                $args[] = $req->jwtArrayClaim($claimName);
                break;
            default:
                if ($info->isNullable()) {
                    $args[] = null;
                } else {
                    $errorTips = self::$err1 . ', reason: unsupported jwt claim type [%s]';

                    throw new RuntimeException(sprintf(
                        $errorTips,
                        $req->getRouteRule()->getHandler(),
                        $info->getName(),
                        $info->getType(),
                        $info->getType()
                    ));
                }

                break;
        }
    }

    private static function injectPathVariable(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $name = $info->getPathVariableName();

        switch ($info->getType()) {
            case 'int':
                $args[] = $req->pathVariableAsInt($name);
                break;
            case 'float':
                $args[] = $req->pathVariableAsFloat($name);
                break;
            case 'bool':
                $args[] = $req->pathVariableAsBoolean($name);
                break;
            case 'string':
                $args[] = $req->pathVariableAsString($name);
                break;
            default:
                if ($info->isNullable()) {
                    $args[] = null;
                } else {
                    $errorTips = self::$err1 . ', reason: unsupported path variable type [%s]';

                    throw new RuntimeException(sprintf(
                        $errorTips,
                        $req->getRouteRule()->getHandler(),
                        $info->getName(),
                        $info->getType(),
                        $info->getType()
                    ));
                }

                break;
        }
    }

    private static function injectRequestParam(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $name = $info->getRequestParamName();

        switch ($info->getType()) {
            case 'int':
                $args[] = $req->requestParamAsInt($name);
                break;
            case 'float':
                $args[] = $req->requestParamAsFloat($name);
                break;
            case 'bool':
                $args[] = $req->requestParamAsBoolean($name);
                break;
            case 'string':
                $args[] = trim($req->requestParamAsString($name));
                break;
            case 'array':
                $args[] = $req->requestParamAsArray($name);
                break;
            default:
                if ($info->isNullable()) {
                    $args[] = null;
                } else {
                    $errorTips = self::$err1 . ', reason: unsupported request param type [%s]';

                    throw new RuntimeException(sprintf(
                        $errorTips,
                        $req->getRouteRule()->getHandler(),
                        $info->getName(),
                        $info->getType(),
                        $info->getType()
                    ));
                }

                break;
        }
    }

    private static function injectParamMap(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        if ($info->getType() !== 'array') {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            throw new RuntimeException(sprintf(self::$err1, $req->getRouteRule()->getHandler(), $info->getName(), $info->getType()));
        }

        $isGet = strtoupper($req->getMethod()) === 'GET';
        $contentType = $req->getHeader('Content-Type');
        $isJsonPayload = stripos($contentType, 'application/json') !== false;

        $isXmlPayload = stripos($contentType, 'application/xml') !== false ||
            stripos($contentType, 'text/xml') !== false;

        if ($isGet) {
            $map1 = $req->getQueryParams();
        } else if ($isJsonPayload) {
            $map1 = JsonUtils::mapFrom($req->getRawBody());
        } else if ($isXmlPayload) {
            $map1 = StringUtils::xml2assocArray($req->getRawBody());
        } else {
            $map1 = array_merge($req->getQueryParams(), $req->getFormData());
        }

        if (!is_array($map1)) {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            throw new RuntimeException(sprintf(self::$err1, $req->getRouteRule()->getHandler(), $info->getName(), $info->getType()));
        }

        foreach ($map1 as $key => $val) {
            if (!is_string($val) || is_numeric($val)) {
                continue;
            }

            $map1[$key] = trim($val);
        }

        $rules = $info->getParamMapRules();
        $args[] = empty($rules) ? $map1 : ArrayUtils::requestParams($map1, $rules);
    }

    private static function injectUploadedFile(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $formFieldName = $info->getFormFieldName();

        try {
            $uploadFile = $req->getUploadedFiles()[$formFieldName];
        } catch (Throwable) {
            $uploadFile = null;
        }

        if (!($uploadFile instanceof UploadedFile)) {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            throw new RuntimeException(sprintf(self::$err1, $req->getRouteRule()->getHandler(), $info->getName(), $info->getType()));
        }

        $args[] = $uploadFile;
    }

    private static function injectRequestBody(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        if ($info->getType() !== 'string') {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            throw new RuntimeException(sprintf(self::$err1, $req->getRouteRule()->getHandler(), $info->getName(), $info->getType()));
        }

        $payload = $req->getRawBody();
        $map1 = JsonUtils::mapFrom($payload);

        if (is_array($map1) && ArrayUtils::isAssocArray($map1)) {
            foreach ($map1 as $key => $val) {
                if (!is_string($val) || is_numeric($val)) {
                    continue;
                }

                $map1[$key] = trim($val);
            }

            $payload = JsonUtils::toJson(empty($map1) ? new stdClass() : $map1);
        }

        $args[] = $payload;
    }

    private static function injectDto(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $logger = MgBoot::getRuntimeLogger();
        $debugMode = AppConf::getBoolean('logging.enable-dto-bind-log');
        $fmt1 = self::$err1 . ', reason: %s';
        $isGet = strtoupper($req->getMethod()) === 'GET';
        $contentType = $req->getHeader('Content-Type');
        $isJsonPayload = stripos($contentType, 'application/json') !== false;

        $isXmlPayload = stripos($contentType, 'application/xml') !== false ||
            stripos($contentType, 'text/xml') !== false;

        if ($isGet) {
            $map1 = $req->getQueryParams();
        } else if ($isJsonPayload) {
            $map1 = JsonUtils::mapFrom($req->getRawBody());
        } else if ($isXmlPayload) {
            $map1 = StringUtils::xml2assocArray($req->getRawBody());
        } else {
            $map1 = array_merge($req->getQueryParams(), $req->getFormData());
        }

        if ($debugMode) {
            $logger->info('dto bind, param map: ' . JsonUtils::toJson($map1));
        }

        if (!is_array($map1)) {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            $errorTips = sprintf(
                $fmt1,
                $req->getRouteRule()->getHandler(),
                $info->getName(),
                $info->getDtoClassName(),
                'param map is empty'
            );

            throw new RuntimeException($errorTips);
        }

        $className = $info->getDtoClassName();

        try {
            $bean = new $className();
        } catch (Throwable) {
            $bean = null;
        }

        if (!is_object($bean)) {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            $errorTips = sprintf(
                $fmt1,
                $req->getRouteRule()->getHandler(),
                $info->getName(),
                $info->getDtoClassName(),
                '无法实例化 dto 对象'
            );

            throw new RuntimeException($errorTips);
        }

        list($success, $errorTips) = self::mapToBean($bean, $map1);

        if (!$success) {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            $errorTips = sprintf(
                $fmt1,
                $req->getRouteRule()->getHandler(),
                $info->getName(),
                $info->getDtoClassName(),
                $errorTips
            );

            throw new RuntimeException($errorTips);
        }

        $args[] = $bean;
    }

    private static function mapToBean(object $bean, array $map1): array
    {
        try {
            $clazz = new ReflectionClass($bean);
        } catch (Throwable $ex) {
            return [false, $ex->getMessage()];
        }

        try {
            $fields = $clazz->getProperties(ReflectionProperty::IS_PRIVATE);
        } catch (Throwable $ex) {
            return [false, $ex->getMessage()];
        }

        try {
            $methods = $clazz->getMethods(ReflectionMethod::IS_PUBLIC);
        } catch (Throwable $ex) {
            return [false, $ex->getMessage()];
        }

        foreach ($fields as $field) {
            $fieldName = strtolower($field->getName());
            $setter = null;

            foreach ($methods as $method) {
                $methodName = strtolower($method->getName());

                if ($methodName === "set$fieldName") {
                    $setter = $method;
                    break;
                }
            }

            if (!($setter instanceof ReflectionMethod)) {
                continue;
            }

            $fieldType = $field->getType();

            if ($fieldType instanceof ReflectionType) {
                $nullbale = $fieldType->allowsNull();

                if ($fieldType instanceof ReflectionNamedType) {
                    $fieldType = strtolower($fieldType->getName());
                } else {
                    $fieldType = '';
                }
            } else {
                $nullbale = true;
                $fieldType = '';
            }

            $mapValue = self::getMapValueByProperty($map1, $field);

            if ($mapValue === null) {
                if (!$nullbale) {
                    return [false, 'fail to get value from param map for field: ' . $field->getName()];
                }

                try {
                    $setter->invoke($bean, null);
                } catch (Throwable $ex) {
                    return [false, $ex->getMessage()];
                }

                continue;
            }

            switch ($fieldType) {
                case 'int':
                    try {
                        $setter->invoke($bean, Cast::toInt($mapValue, 0));
                    } catch (Throwable $ex) {
                        return [false, $ex->getMessage()];
                    }

                    break;
                case 'float':
                    try {
                        $setter->invoke($bean, Cast::toFloat($mapValue, 0.0));
                    } catch (Throwable $ex) {
                        return [false, $ex->getMessage()];
                    }

                    break;
                case 'boolean':
                case 'bool':
                    try {
                        $setter->invoke($bean, Cast::toBoolean($mapValue));
                    } catch (Throwable $ex) {
                        return [false, $ex->getMessage()];
                    }

                    break;
                case 'string':
                    try {
                        $setter->invoke($bean, Cast::toString($mapValue));
                    } catch (Throwable $ex) {
                        return [false, $ex->getMessage()];
                    }

                    break;
                default:
                    try {
                        $setter->invoke($bean, $mapValue);
                    } catch (Throwable $ex) {
                        return [false, $ex->getMessage()];
                    }

                    break;
            }
        }

        return [true, ''];
    }

    private static function getMapValueByProperty(array $map1, ReflectionProperty $property): mixed
    {
        $mapKey = strtolower(self::getMapKeyByProperty($property));

        if (empty($mapKey)) {
            return null;
        }

        foreach ($map1 as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $key = strtolower(strtr($key, ['-' => '', '_' => '']));

            if ($key === $mapKey) {
                return $value;
            }

            if (StringUtils::ensureLeft($key, 'is') === StringUtils::ensureLeft($mapKey, 'is')) {
                return $value;
            }
        }

        return null;
    }

    private static function getMapKeyByProperty(ReflectionProperty $property): string
    {
        try {
            $annos = $property->getAttributes();
        } catch (Throwable) {
            $annos = [];
        }

        $anno = null;

        foreach ($annos as $it) {
            if (str_ends_with($it->getName(), 'MapKey')) {
                $anno = $it;
                break;
            }
        }

        if (is_object($anno) && method_exists($anno, 'getValue')) {
            try {
                $mapKey = Cast::toString($anno->getValue());
            } catch (Throwable) {
                $mapKey = '';
            }

            if ($mapKey !== '') {
                return $mapKey;
            }
        }

        $pname = $property->getName();
        return is_string($pname) ? $pname : '';
    }
}
