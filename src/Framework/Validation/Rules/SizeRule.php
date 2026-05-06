<?php

namespace App\Framework\Validation\Rules;

class SizeRule extends BaseValidationRule
{
    protected int|float $size;

    public function validate($value, array $data = []): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) === $this->size;
        }

        if (is_numeric($value)) {
            return $value == $this->size;
        }

        if (is_array($value)) {
            return count($value) === $this->size;
        }

        return false;
    }

    public function setParameters(array $parameters): void
    {
        $this->size = $parameters[0] ?? 0;
    }

    public function getMessage(): string
    {
        return str_replace(':size', $this->size, $this->getDefaultMessage());
    }

    protected function getDefaultMessage(): string
    {
        return 'This field must be exactly :size.';
    }
}