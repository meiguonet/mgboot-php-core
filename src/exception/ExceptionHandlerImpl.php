<?php

namespace mgboot\core\exception;

use mgboot\core\http\server\response\JsonResponse;
use mgboot\core\http\server\response\ResponsePayload;
use Throwable;

final class ExceptionHandlerImpl implements ExceptionHandler
{
    private string $clazz;

    private function __construct(string $clazz)
    {
        $this->clazz = $clazz;
    }

    private function __clone(): void
    {
    }

    public static function create(string $clazz): self
    {
        return new self($clazz);
    }

    public function getExceptionClassName(): string
    {
        return $this->clazz;
    }

    public function handleException(Throwable $ex): ?ResponsePayload
    {
        $payload = null;

        switch (get_class($ex)) {
            case AccessTokenExpiredException::class:
                if ($ex instanceof AccessTokenExpiredException) {
                    $code = 1003;
                    $msg = $ex->getMessage();
                    $payload = JsonResponse::withPayload(compact('code', 'msg'));
                }

                break;
            case AccessTokenInvalidException::class:
                if ($ex instanceof AccessTokenInvalidException) {
                    $code = 1002;
                    $msg = $ex->getMessage();
                    $payload = JsonResponse::withPayload(compact('code', 'msg'));
                }

                break;
            case DataValidateException::class:
                if ($ex instanceof DataValidateException) {
                    $code = 1006;
                    $msg = $ex->getErrorTips();
                    $validateErrors = $ex->getValidateErrors();
                    $payload = JsonResponse::withPayload(compact('code', 'msg', 'validateErrors'));
                }

                break;
            case RequireAccessTokenException::class:
                if ($ex instanceof RequireAccessTokenException) {
                    $code = 1001;
                    $msg = $ex->getMessage();
                    $payload = JsonResponse::withPayload(compact('code', 'msg'));
                }

                break;
        }

        return $payload;
    }
}
