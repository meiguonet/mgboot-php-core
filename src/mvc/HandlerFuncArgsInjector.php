<?php

namespace mgboot\core\mvc;

use Lcobucci\JWT\Token;
use mgboot\Cast;
use mgboot\core\http\server\Request;
use mgboot\http\server\UploadedFile;
use mgboot\util\ArrayUtils;
use mgboot\util\JsonUtils;
use mgboot\util\ReflectUtils;
use mgboot\util\StringUtils;
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
    private static string $fmt1 = 'fail to inject arg for handler function %s, name: %s, type: %s';

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
                    self::thowException($handler, $info);
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

            self::thowException($handler, $info);
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
                    $fmt = '@@fmt:' . self::$fmt1 . ', reason: unsupported jwt claim type [%s]';
                    $handler = $req->getRouteRule()->getHandler();
                    self::thowException($handler, $info, $fmt, $info->getType());
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
                    $fmt = '@@fmt:' . self::$fmt1 . ', reason: unsupported path variable type [%s]';
                    $handler = $req->getRouteRule()->getHandler();
                    self::thowException($handler, $info, $fmt, $info->getType());
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
                    $fmt = '@@fmt:' . self::$fmt1 . ', reason: unsupported request param type [%s]';
                    $handler = $req->getRouteRule()->getHandler();
                    self::thowException($handler, $info, $fmt, $info->getType());
                }

                break;
        }
    }

    private static function injectParamMap(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $handler = $req->getRouteRule()->getHandler();

        if ($info->getType() !== 'array') {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            self::thowException($handler, $info);
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

            self::thowException($handler, $info);
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

            $handler = $req->getRouteRule()->getHandler();
            self::thowException($handler, $info);
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

            $handler = $req->getRouteRule()->getHandler();
            self::thowException($handler, $info);
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
        $handler = $req->getRouteRule()->getHandler();
        $fmt = '@@fmt:' . self::$fmt1 . ', reason: %s';
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

            self::thowException($handler, $info, $fmt, 'param map is empty');
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

            self::thowException($handler, $info, $fmt, '无法实例化 dto 对象');
        }

        list($success, $errorTips) = self::mapToBean($bean, $map1);

        if (!$success) {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            self::thowException($handler, $info, $fmt, $errorTips);
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
            $setter = ReflectUtils::getSetter($field, $methods);

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

            $mapValue = ReflectUtils::getMapValueByProperty($map1, $field);

            if ($mapValue === null) {
                if (!$nullbale) {
                    return [false, "fail to get value from param map for field: {$field->getName()}"];
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

    private static function thowException(string $handler, HandlerFuncArgInfo $info, mixed... $args): void
    {
        $fmt = self::$fmt1;
        $params = [$handler, $info->getName(), $info->getType()];

        if (!empty($args)) {
            if (is_string($args[0]) && str_starts_with('@@fmt:')) {
                $fmt = str_replace('@@fmt:', '', array_shift($args));

                if (!empty($args)) {
                    array_push($params, ...$args);
                }
            } else {
                array_push($params, ...$args);
            }
        }

        $errorTips = sprintf($fmt, ...$params);
        throw new RuntimeException($errorTips);
    }
}
