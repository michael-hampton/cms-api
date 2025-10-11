<?php

namespace App\Framework\Validation\Rules;

class NullableRule extends BaseValidationRule
{
    public function validate($value, array $data = []): bool
    {
        // Always passes, since 'nullable' doesn't fail validation itself
        return true;
    }

    protected function getDefaultMessage(): string
    {
        // Normally this rule doesn't even show a message,
        // but we'll include one for completeness
        return "This field may be null or empty.";
    }
}
