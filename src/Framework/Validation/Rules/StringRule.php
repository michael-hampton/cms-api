<?php

namespace App\Framework\Validation\Rules;

use App\Framework\Validation\ValidationRuleInterface;

class StringRule extends BaseValidationRule
{
    public function validate($value, array $data = []): bool
    {
        return is_string($value) || is_numeric($value);
    }

    public function getMessage(): string
    {
        return 'The field must be a string.';
    }

    public function setParameters(array $parameters): void {}

    protected function getDefaultMessage(): string
    {
        return 'This field must be a string';
    }
}