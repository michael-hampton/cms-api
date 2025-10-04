<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class HeadingLevelRule extends BaseValidationRule
{
    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_numeric($value) && $value >= 1 && $value <= 6;
    }

    protected function getDefaultMessage(): string
    {
        return 'Heading level must be between 1 and 6';
    }
}