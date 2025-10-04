<?php

namespace App\Framework\Validation\Rules;

class RegexRule extends BaseValidationRule
{
    private $pattern;

    public function __construct(string $pattern = '')
    {
        $this->pattern = $pattern;
    }

    public function setParameters(array $parameters): void
    {
        if (!empty($parameters)) {
            $this->pattern = $parameters[0];
        }
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return preg_match($this->pattern, $value) === 1;
    }

    protected function getDefaultMessage(): string
    {
        return 'This field format is invalid';
    }
}