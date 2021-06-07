<?php

namespace mgboot\core\http\middleware;

use mgboot\core\MgBoot;
use mgboot\core\mvc\RoutingContext;

class RequestLogMiddleware implements Middleware
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
        if (!MgBoot::isRequestLogEnabled()) {
            return;
        }

        if (!$ctx->next()) {
            return;
        }

        $request = $ctx->getRequest();
        $clientIp = $request->getClientIp();
        $httpMethod = $request->getMethod();
        $requestUrl = $request->getRequestUrl(true);

        if ($httpMethod === '' || $requestUrl === '' || $clientIp === '') {
            return;
        }

        $logger = MgBoot::getRequestLogLogger();
        $logger->info("$httpMethod $requestUrl from $clientIp");
        $requestBody = $request->getRawBody();

        if ($requestBody !== '') {
            $logger->debug($requestBody);
        }
    }

    public function postHandle(RoutingContext $ctx): void
    {
    }
}
