<?php

namespace mgboot\core\exception;

use mgboot\core\http\server\response\JsonResponse;
use mgboot\core\http\server\response\ResponsePayload;
use Throwable;

class RequireAccessTokenExceptionHandler implements ExceptionHandler
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
        return RequireAccessTokenException::class;
    }

    public function handleException(Throwable $ex): ?ResponsePayload
    {
        $map1 = [
            'code' => 1001,
            'msg' => '安全令牌缺失'
        ];

        return JsonResponse::withPayload($map1);
    }
}
