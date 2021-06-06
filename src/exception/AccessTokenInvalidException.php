<?php

namespace mgboot\core\exception;

use RuntimeException;

final class AccessTokenInvalidException extends RuntimeException
{
    public function __construct(string $errorTips = '')
    {
        if ($errorTips === '') {
            $errorTips = '不是有效安全令牌';
        }

        parent::__construct($errorTips);
    }
}
