<?php

namespace App\Framework\Validation\Rules;

class RequiredIfRule extends BaseValidationRule
{
    private $field;
    private $expectedValue;

    public function __construct(string $field = '', $expectedValue = '')
    {
        $this->field = $field;
        $this->expectedValue = $expectedValue;
    }

    public function setParameters(array $parameters): void
    {
        if (count($parameters) >= 2) {
            $this->field = $parameters[0];
            $this->expectedValue = $parameters[1];
        }
    }

    public function validate($value, array $data = []): bool
    {
        $actualValue = $data[$this->field] ?? null;

        if ($actualValue != $this->expectedValue) {
            return true;
        }

        return !($value === null || $value === '');
    }

    protected function getDefaultMessage(): string
    {
        return "This field is required when {$this->field} is {$this->expectedValue}";
    }
}