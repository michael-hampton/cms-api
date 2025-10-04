<?php

namespace App\Framework\Validation\Rules;

use Exception;

class MinRule extends BaseValidationRule
{
    private float $minAmount;

    public function __construct(float $minAmount = 1)
    {
        $this->parameters = [$minAmount];
        $this->minAmount = $minAmount;
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (count($this->parameters) < 1) {
            throw new Exception('Min rule requires minimum value parameter');
        }

        $min = $this->parameters[0];

        if (is_array($value)) {
            // cast $min to int explicitly for count comparisons
            return count($value) >= (int)$min;
        }

        return is_numeric($value) && $value >= $min; // keep float comparison
    }

    protected function getDefaultMessage(): string
    {
        $min = $this->parameters[0] ?? 0;
        return "This field must be at least {$min}";
    }
}