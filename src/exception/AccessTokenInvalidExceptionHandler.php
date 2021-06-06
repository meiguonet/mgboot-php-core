<?php

namespace mgboot\core\exception;

use mgboot\core\http\server\response\JsonResponse;
use mgboot\core\http\server\response\ResponsePayload;
use Throwable;

final class AccessTokenInvalidExceptionHandler implements ExceptionHandler
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
        return AccessTokenInvalidException::class;
    }

    public function handleException(Throwable $ex): ?ResponsePayload
    {
        $map1 = [
            'code' => 1002,
            'msg' => '不是有效的安全令牌'
        ];

        return JsonResponse::withPayload($map1);
    }
}
