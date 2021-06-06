<?php

namespace mgboot\core\http\middleware;

use mgboot\core\mvc\RoutingContext;

interface Middleware
{
    const PRE_HANDLE_MIDDLEWARE = 1;
    const POST_HANDLE_MIDDLEWARE = 2;

    public function getType(): int;

    public function preHandle(RoutingContext $ctx): void;

    public function postHandle(RoutingContext $ctx): void;
}
