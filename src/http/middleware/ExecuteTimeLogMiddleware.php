<?php

namespace mgboot\core\http\middleware;

use mgboot\core\http\server\Request;
use mgboot\core\MgBoot;
use mgboot\core\mvc\RoutingContext;
use mgboot\util\StringUtils;

class ExecuteTimeLogMiddleware implements Middleware
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
        return Middleware::POST_HANDLE_MIDDLEWARE;
    }

    public function getOrder(): int
    {
        return Middleware::LOWEST_ORDER;
    }

    public function preHandle(RoutingContext $ctx): void
    {
    }

    public function postHandle(RoutingContext $ctx): void
    {
        if (!MgBoot::isExecuteTimeLogEnabled()) {
            return;
        }

        if ($ctx->hasError()) {
            return;
        }

        $request = $ctx->getRequest();
        $routeRule = $request->getRouteRule();
        $httpMethod = $request->getMethod();
        $requestUrl = $request->getRequestUrl(true);
        $clazz = StringUtils::substringBefore($routeRule->getHandler(), '@');
        $clazz = StringUtils::ensureLeft($clazz, "\\");
        $methodName = StringUtils::substringAfterLast($routeRule->getHandler(), '@');

        if ($httpMethod === '' || $requestUrl === '' || $clazz === '' || $methodName === '') {
            return;
        }

        $handler = "$clazz@$methodName(...)";
        $elapsedTime = $this->calcElapsedTime($request);
        $logger = MgBoot::getExecuteTimeLogLogger();
        $logger->info("$httpMethod $requestUrl, handler: $handler, total elapsed time: $elapsedTime.");
        $ctx->getResponse()->addExtraHeader('X-Response-Time', $elapsedTime);
    }

    private function calcElapsedTime(Request $request): string
    {
        $n1 = bcmul(bcsub(microtime(true), $request->getExecStart(), 6), 1000, 6);

        if (bccomp($n1, 1000, 6) !== 1) {
            $n1 = (int) StringUtils::substringBefore($n1, '.');
            $n1 = $n1 < 1 ? 1 : $n1;
            return "{$n1}ms";
        }

        $n1 = bcdiv($n1, 1000, 6);
        $n1 = rtrim($n1, '0');
        $n1 = rtrim($n1, '.');
        return "{$n1}s";
    }
}
