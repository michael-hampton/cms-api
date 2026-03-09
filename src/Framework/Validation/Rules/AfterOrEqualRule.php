<?php

namespace App\Framework\Validation\Rules;

class AfterOrEqualRule extends BaseValidationRule
{
    private string $otherField = '';

    public function setParameters(array $parameters): void
    {
        $this->otherField = $parameters[0] ?? '';
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Let nullable/required handle empty values
        }

        $otherValue = $data[$this->otherField] ?? null;

        if ($otherValue === null || $otherValue === '') {
            return true; // Cannot compare against missing field
        }

        $date = strtotime($value);
        $otherDate = strtotime($otherValue);

        if ($date === false || $otherDate === false) {
            return false;
        }

        return $date >= $otherDate;
    }

    protected function getDefaultMessage(): string
    {
        return "This date must be on or after the {$this->otherField} date.";
    }
}