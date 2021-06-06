<?php

namespace mgboot\core\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class JwtAuth
{
    private string $settingsKey;

    public function __construct(string $arg0)
    {
        $this->settingsKey = $arg0;
    }

    /**
     * @return string
     */
    public function getSettingsKey(): string
    {
        return $this->settingsKey;
    }
}
