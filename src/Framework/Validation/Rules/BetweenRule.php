<?php

namespace App\Framework\Validation\Rules;

class BetweenRule extends BaseValidationRule
{
    private $min;
    private $max;

    public function __construct($min = 0, $max = 100)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function setParameters(array $parameters): void
    {
        if (count($parameters) >= 2) {
            $this->min = $parameters[0];
            $this->max = $parameters[1];
        }
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_numeric($value)) {
            return $value >= $this->min && $value <= $this->max;
        }

        $length = strlen($value);
        return $length >= $this->min && $length <= $this->max;
    }

    protected function getDefaultMessage(): string
    {
        return "This field must be between {$this->min} and {$this->max}";
    }
}