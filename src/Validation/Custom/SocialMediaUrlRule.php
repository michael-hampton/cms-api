<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class SocialMediaUrlRule extends BaseValidationRule
{
    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        // Allow usernames with @ or full URLs
        if (strpos($value, '@') === 0) {
            return strlen($value) > 1;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function getDefaultMessage(): string
    {
        return 'This field must be a valid social media URL or username';
    }
}