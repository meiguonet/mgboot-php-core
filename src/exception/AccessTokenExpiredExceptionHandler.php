<?php

namespace mgboot\core\exception;

use mgboot\core\http\server\response\JsonResponse;
use mgboot\core\http\server\response\ResponsePayload;
use Throwable;

final class AccessTokenExpiredExceptionHandler implements ExceptionHandler
{
    private function __construct()
    {
    }

    private function __clone(): void
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function getExceptionClassName(): string
    {
        return AccessTokenExpiredException::class;
    }

    public function handleException(Throwable $ex): ?ResponsePayload
    {
        $map1 = [
            'code' => 1003,
            'msg' => '安全令牌已失效'
        ];

        return JsonResponse::withPayload($map1);
    }
}
