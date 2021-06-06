<?php

namespace mgboot\core\annotation;

use Attribute;
use mgboot\common\ArrayUtils;

#[Attribute(Attribute::TARGET_METHOD)]
final class Validate
{
    /**
     * @var string[]
     */
    private array $rules;

    private bool $failfast;

    public function __construct(string|array $rules, bool $failfast = false)
    {
        $_rules = [];

        if (is_string($rules) && $rules !== '') {
            $_rules = preg_split('/[\x20\t]*,[\x20\t]*/', $rules);
        } else if (ArrayUtils::isStringArray($rules)) {
            $_rules = $rules;
        }

        $this->rules = $_rules;
        $this->failfast = $failfast;
    }

    /**
     * @return string[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return bool
     */
    public function isFailfast(): bool
    {
        return $this->failfast;
    }
}
