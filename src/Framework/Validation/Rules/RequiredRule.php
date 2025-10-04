<?php

namespace App\Framework\Validation\Rules;

class RequiredRule extends BaseValidationRule
{
    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return true;
    }

    protected function getDefaultMessage(): string
    {
        return 'This field is required';
    }
}