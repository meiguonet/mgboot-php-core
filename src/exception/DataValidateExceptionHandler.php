<?php

namespace mgboot\core\exception;

use mgboot\core\http\server\response\JsonResponse;
use mgboot\core\http\server\response\ResponsePayload;
use Throwable;

final class DataValidateExceptionHandler implements ExceptionHandler
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
        return DataValidateException::class;
    }

    public function handleException(Throwable $ex): ?ResponsePayload
    {
        if (!($ex instanceof DataValidateException)) {
            return null;
        }

        $map1 = [
            'code' => 1006,
            'msg' => '数据完整性验证错误'
        ];

        if (!empty($ex->getValidateErrors())) {
            $map1['validateErrors'] = $ex->getValidateErrors();
        }

        return JsonResponse::withPayload($map1);
    }
}
