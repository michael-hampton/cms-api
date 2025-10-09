<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class AlignmentRule extends BaseValidationRule
{
    private $validAlignments = [
        'left',
        'center',
        'right',
        'fullscreen'
    ];

    protected function getDefaultMessage(): string
    {
        return 'The alignment must be one of: ' . implode(', ', $this->validAlignments);
    }

    public function validate($value, array $data = []): bool
    {
        if (empty($value)) {
            return true; // Allow empty, use RequiredRule if needed
        }

        return in_array(strtolower($value), $this->validAlignments, true);
    }
}