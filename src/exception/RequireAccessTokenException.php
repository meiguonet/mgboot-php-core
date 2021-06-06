<?php

namespace mgboot\core\exception;

use RuntimeException;

class RequireAccessTokenException extends RuntimeException
{
    public function __construct(string $errorTips = '')
    {
        if ($errorTips === '') {
            $errorTips = '安全令牌缺失';
        }

        parent::__construct($errorTips);
    }
}
