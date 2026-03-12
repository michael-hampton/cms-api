<?php

namespace App\Framework\Validation\Rules;

class MinLengthRule extends BaseValidationRule
{
    private $minLength;

    public function __construct(int $minLength = 1)
    {
        $this->minLength = $minLength;
    }

    public function setParameters(array $parameters): void
    {
        if (!empty($parameters)) {
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

        // For scalar values, check string length
        return strlen((string)$value) >= $this->minLength;
    }

    /**
     * Count all elements in a multidimensional array recursively
     */
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
        $min = $this->parameters[0] ?? 1;
        return "This field must be at least {$min} characters";
    }
}