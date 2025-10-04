<?php

namespace App\Framework\Validation\Rules;

class NumericRule extends BaseValidationRule
{
    public function __construct() {}

    public function setParameters(array $parameters): void {}

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_numeric($value);
    }

    protected function getDefaultMessage(): string
    {
        return 'This field must be numeric';
    }
}