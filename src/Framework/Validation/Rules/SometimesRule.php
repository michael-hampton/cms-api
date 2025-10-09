<?php

namespace App\Framework\Validation\Rules;

class SometimesRule extends BaseValidationRule
{
    public function validate($value, array $data = []): bool
    {
        // Sometimes rule always passes - it's a marker rule that tells the validator
        // to only validate the field if it exists in the data
        return true;
    }

    protected function getDefaultMessage(): string
    {
        return '';
    }
}