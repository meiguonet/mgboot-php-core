<?php

namespace mgboot\core\exception;

use mgboot\core\http\server\response\ResponsePayload;
use Throwable;

interface ExceptionHandler
{
    public function getExceptionClassName(): string;

    public function handleException(Throwable $ex): ?ResponsePayload;
}
