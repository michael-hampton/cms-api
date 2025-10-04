<?php

namespace App\Framework\Validation\Rules;

class InRule extends BaseValidationRule
{
    private $allowedValues;

    public function __construct(array $allowedValues = [])
    {
        $this->allowedValues = $allowedValues;
    }

    public function setParameters(array $parameters): void
    {
        if (!empty($parameters)) {
            $this->allowedValues = $parameters;
        }
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array($value, $this->allowedValues, true);
    }

    protected function getDefaultMessage(): string
    {
        return 'Invalid value. Allowed values: ' . implode(', ', $this->allowedValues);
    }
}