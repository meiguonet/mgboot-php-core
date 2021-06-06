<?php

namespace mgboot\core\exception;

use RuntimeException;

final class DataValidateException extends RuntimeException
{
    private string $errorTips;
    private array $validateErrors;

    public function __construct(array $validateErrors = [], string $errorTips = '')
    {
        if ($errorTips === '') {
            $errorTips = '数据完整性验证错误';
        }

        parent::__construct($errorTips);
        $this->errorTips = $errorTips;
        $this->validateErrors = $validateErrors;
    }

    /**
     * @return string
     */
    public function getErrorTips(): string
    {
        return $this->errorTips;
    }

    /**
     * @return array
     */
    public function getValidateErrors(): array
    {
        return $this->validateErrors;
    }
}
