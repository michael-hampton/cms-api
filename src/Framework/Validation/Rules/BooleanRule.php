<?php

namespace App\Framework\Validation\Rules;

class BooleanRule extends BaseValidationRule
{
    public function validate($value, array $data = []): bool
    {
        return is_bool($value) || $value === '0' || $value === '1' || $value === 0 || $value === 1 || in_array($value, ['true', 'false']);
    }

    protected function getDefaultMessage(): string
    {
        return 'This field must be a boolean value';
    }
}