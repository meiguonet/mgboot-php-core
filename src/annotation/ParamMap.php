<?php

namespace mgboot\core\annotation;

use Attribute;
use mgboot\common\ArrayUtils;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class ParamMap
{
    /**
     * @var string[]
     */
    private array $rules;

    public function __construct(string|array $arg0 = [])
    {
        $rules = [];

        if (is_string($arg0) && $arg0 !== '') {
            $rules = preg_split('/[\x20\t]*,[\x20\t]*/', $arg0);
        } else if (ArrayUtils::isStringArray($rules)) {
            $rules = $arg0;
        }

        $this->rules = $rules;
    }

    /**
     * @return string[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
