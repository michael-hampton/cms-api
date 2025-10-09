<?php

namespace App\Framework\Validation\Rules;

class ConfirmedRule extends BaseValidationRule
{
    private $field;

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function validate($value, array $data = []): bool
    {
        $confirmationField = $this->field . '_confirmation';

        if (!isset($data[$confirmationField])) {
            return false;
        }

        return $value === $data[$confirmationField];
    }

    protected function getDefaultMessage(): string
    {
        return 'The confirmation does not match';
    }
}