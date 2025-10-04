<?php

namespace App\Framework\Validation\Rules;


class IntegerRule extends BaseValidationRule
{
    public function __construct() {}

    public function setParameters(array $parameters): void {}

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function getDefaultMessage(): string
    {
        return 'This field must be an integer';
    }
}