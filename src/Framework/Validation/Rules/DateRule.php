<?php

namespace App\Framework\Validation\Rules;

class DateRule extends BaseValidationRule
{
    public function __construct() {}

    public function setParameters(array $parameters): void {}

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        return strtotime($value) !== false;
    }

    protected function getDefaultMessage(): string
    {
        return 'Invalid date format';
    }
}