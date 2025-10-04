<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class DividerStyleRule extends BaseValidationRule
{
    private $allowedStyles = [
        'solid',
        'dashed',
        'dotted',
        'double',
        'thick',
        'thin',
        'decorative'
    ];

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Optional field, defaults to 'solid'
        }

        return in_array($value, $this->allowedStyles, true);
    }

    protected function getDefaultMessage(): string
    {
        return 'The divider style must be one of: ' . implode(', ', $this->allowedStyles);
    }

    public function getAllowedStyles(): array
    {
        return $this->allowedStyles;
    }
}