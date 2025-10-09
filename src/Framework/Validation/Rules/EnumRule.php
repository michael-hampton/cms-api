<?php

namespace App\Framework\Validation\Rules;

class EnumRule extends BaseValidationRule
{
    protected string $enumClass;

    public function __construct(string $enumClass)
    {
        $this->enumClass = $enumClass;
    }

    public function validate($value, array $data = []): bool
    {
        if (empty($value)) {
            return true;
        }

        if (!enum_exists($this->enumClass)) {
            throw new \InvalidArgumentException("Class {$this->enumClass} is not an enum");
        }

        return $this->enumClass::isValid($value);
    }

    protected function getDefaultMessage(): string
    {
        $values = $this->enumClass::values();
        return 'The value must be one of: ' . implode(', ', $values);
    }
}