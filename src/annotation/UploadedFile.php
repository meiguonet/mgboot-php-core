<?php

namespace mgboot\core\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class UploadedFile
{
    private string $formFieldName;

    public function __construct(string $arg0)
    {
        $this->formFieldName = $arg0;
    }

    public function getFormFieldName(): string
    {
        return $this->formFieldName;
    }
}
