<?php

namespace mgboot\core\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class MapKey
{
    private string $value;

    public function __construct(string $arg0)
    {
        $this->value = $arg0;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
