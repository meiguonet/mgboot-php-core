<?php

namespace mgboot\core\annotation;

use Attribute;
use mgboot\constant\RequestParamSecurityMode as SecurityMode;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class RequestParam
{
    private string $name;
    private bool $decimal;
    private int $securityMode;

    public function __construct(string $name = '', bool $decimal = false, int $securityMode = SecurityMode::STRIP_TAGS)
    {
        if (!in_array($securityMode, [
            SecurityMode::NONE,
            SecurityMode::HTML_PURIFY,
            SecurityMode::STRIP_TAGS
        ])) {
            $securityMode = SecurityMode::STRIP_TAGS;
        }

        $this->name = $name;
        $this->decimal = $decimal;
        $this->securityMode = $securityMode;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isDecimal(): bool
    {
        return $this->decimal;
    }

    /**
     * @return int
     */
    public function getSecurityMode(): int
    {
        return $this->securityMode;
    }
}
