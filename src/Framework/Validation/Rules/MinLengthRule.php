<?php

namespace App\Framework\Validation\Rules;

class MinLengthRule extends BaseValidationRule
{
    private int $minLength;

    public function __construct(int $minLength = 1)
    {
        $this->minLength = $minLength;
        $this->parameters = [$minLength];
    }

    public function setParameters(array $parameters): void
    {
        parent::setParameters($parameters);

        if ($parameters !== []) {
            $this->minLength = (int)$parameters[0];
        }
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_array($value)) {
            return count($value) >= $this->minLength;
        }

        return strlen((string)$value) >= $this->minLength;
    }

    protected function countElementsRecursive(array $array): int
    {
        $count = 0;

        foreach ($array as $item) {
            if (is_array($item)) {
                $count += $this->countElementsRecursive($item);
            } else {
                $count++;
            }
        }

        return $count;
    }

    protected function getDefaultMessage(): string
    {
        return "This field must be at least {$this->minLength} characters";
    }
}
