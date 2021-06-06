<?php

namespace mgboot\core\http\server\response;

use mgboot\core\exception\HttpError;

interface ResponsePayload
{
    public function getContentType(): string;

    public function getContents(): string|HttpError;
}
