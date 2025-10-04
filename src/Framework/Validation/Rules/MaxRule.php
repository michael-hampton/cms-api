<?php

namespace App\Framework\Validation\Rules;

use Exception;

class MaxRule extends BaseValidationRule
{
    public function __construct(int $maxAmount = 100) {
        $this->parameters = [$maxAmount];
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (count($this->parameters) < 1) {
            throw new Exception('Max rule requires maximum value parameter');
        }

        $max = $this->parameters[0];
        return is_numeric($value) && $value <= $max;
    }

    protected function getDefaultMessage(): string
    {
        $max = $this->parameters[0] ?? 100;
        return "This field must not exceed {$max}";
    }
}