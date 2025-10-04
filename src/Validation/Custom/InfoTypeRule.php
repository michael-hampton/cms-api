<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class InfoTypeRule extends BaseValidationRule
{
    private $allowedTypes = [
        'item-list',
        'instructions',
        'ingredients',
        'none'
    ];

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array($value, $this->allowedTypes, true);
    }

    protected function getDefaultMessage(): string
    {
        return 'The info type must be one of: ' . implode(', ', $this->allowedTypes);
    }

    public function getAllowedTypes(): array
    {
        return $this->allowedTypes;
    }
}