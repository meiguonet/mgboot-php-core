<?php

namespace mgboot\core\http\middleware;

use Lcobucci\JWT\Token;
use mgboot\core\exception\AccessTokenExpiredException;
use mgboot\core\exception\AccessTokenInvalidException;
use mgboot\core\exception\RequireAccessTokenException;
use mgboot\core\MgBoot;
use mgboot\core\mvc\RoutingContext;
use mgboot\core\security\JwtSettings;
use mgboot\util\JwtUtils;

class JwtAuthMiddleware implements Middleware
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

        $req = $ctx->getRequest();
        $key = $req->getRouteRule()->getJwtSettingsKey();

        if ($key === '') {
            return;
        }

        $settings = MgBoot::getJwtSettings($key);

        if (!($settings instanceof JwtSettings) || $settings->getIssuer() === '') {
            return;
        }

        $jwt = $ctx->getRequest()->getJwt();

        if (!($jwt instanceof Token)) {
            $ctx->getResponse()->withPayload(new RequireAccessTokenException());
            $ctx->next(false);
            return;
        }

        list($passed, $errCode) = JwtUtils::verify($jwt, $settings->getIssuer());

        if (!$passed) {
            $ex = match ($errCode) {
                -1 => new AccessTokenInvalidException(),
                -2 => new AccessTokenExpiredException(),
                default => null,
            };

            $ctx->getResponse()->withPayload($ex);
            $ctx->next(false);
        }
    }

    public function postHandle(RoutingContext $ctx): void
    {
    }
}
