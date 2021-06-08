<?php

namespace mgboot\core\http\middleware;

use mgboot\core\exception\DataValidateException;
use mgboot\core\mvc\RoutingContext;
use mgboot\core\validator\DataValidator;
use mgboot\util\JsonUtils;
use mgboot\util\StringUtils;

class DataValidateMiddleware implements Middleware
{
    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function getType(): int
    {
        return Middleware::PRE_HANDLE_MIDDLEWARE;
    }

    public function getOrder(): int
    {
        return Middleware::HIGHEST_ORDER;
    }

    public function preHandle(RoutingContext $ctx): void
    {
        if (!$ctx->next()) {
            return;
        }

        $request = $ctx->getRequest();
        $routeRule = $request->getRouteRule();
        $validateRules = $routeRule->getValidateRules();

        if (empty($validateRules)) {
            return;
        }

        $failfast = $routeRule->isFailfast();
        $isGet = $request->getMethod() === 'GET';
        $contentType = $request->getHeader('Content-Type');
        $isJsonPayload = stripos($contentType, 'application/json') !== false;

        $isXmlPayload = stripos($contentType, 'application/xml') !== false
            || stripos($contentType, 'text/xml') !== false;

        if ($isGet) {
            $data = $request->getQueryParams();
        } else if ($isJsonPayload) {
            $data = JsonUtils::mapFrom($request->getRawBody());
        } else if ($isXmlPayload) {
            $data = StringUtils::xml2assocArray($request->getRawBody());
        } else {
            $data = array_merge($request->getQueryParams(), $request->getFormData());
        }

        if (!is_array($data)) {
            $data = [];
        }

        $result = DataValidator::validate($data, $validateRules, $failfast);

        if ($failfast && is_string($result) && $result !== '') {
            $ctx->getResponse()->withPayload(new DataValidateException(errorTips: $result));
            $ctx->next(false);
            return;
        }

        if (!$failfast && is_array($result) && !empty($result)) {
            $ctx->getResponse()->withPayload(new DataValidateException($result));
            $ctx->next(false);
        }
    }

    public function postHandle(RoutingContext $ctx): void
    {
    }
}
