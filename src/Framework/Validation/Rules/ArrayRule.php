<?php

namespace App\Framework\Validation\Rules;

class ArrayRule extends BaseValidationRule
{
    public function validate($value, array $data = []): bool
    {
        return is_array($value);
    }

    protected function getDefaultMessage(): string
    {
        return 'This field must be an array';
    }
}