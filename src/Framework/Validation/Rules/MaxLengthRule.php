<?php

namespace App\Framework\Validation\Rules;

class MaxLengthRule extends BaseValidationRule
{
    private $maxLength;

    public function __construct(int $maxLength = 255)
    {
        $this->maxLength = $maxLength;
    }

    public function setParameters(array $parameters): void
    {
        if (!empty($parameters)) {
            $this->maxLength = (int)$parameters[0];
        }
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null) {
            return true;
        }

        return strlen((string)$value) <= $this->maxLength;
    }

    protected function getDefaultMessage(): string
    {
        $max = $this->parameters[0] ?? 255;
        return "This field must not exceed {$max} characters";
    }
}