<?php

namespace App\Framework\Validation\Rules;


class Accepted extends BaseValidationRule
{
    public function __construct()
    {
    }

    public function setParameters(array $parameters): void
    {
    }

    public function validate($value, array $data = []): bool
    {
        if (!in_array($value, ['yes', 'on', '1', 1, true, 'true'], true)) {
            return false;
        }

        return true;
    }

    protected function getDefaultMessage(): string
    {
        return 'The :attribute must be accepted.';
    }
}