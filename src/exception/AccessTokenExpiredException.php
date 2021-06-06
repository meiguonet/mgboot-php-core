<?php

namespace mgboot\core\exception;

use RuntimeException;

final class AccessTokenExpiredException extends RuntimeException
{
    public function __construct(string $errorTips = '')
    {
        if ($errorTips === '') {
            $errorTips = '安全令牌已失效';
        }

        parent::__construct($errorTips);
    }
}
