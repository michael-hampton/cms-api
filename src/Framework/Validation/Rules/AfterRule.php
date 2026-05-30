<?php

namespace App\Framework\Validation\Rules;

class AfterRule extends BaseValidationRule
{
    private $compareField;

    public function __construct(string $compareField = '')
    {
        $this->compareField = $compareField;
    }

    public function setParameters(array $parameters): void
    {
        if (!empty($parameters)) {
            $this->compareField = $parameters[0];
        }
    }

    public function validate($value, array $data = []): bool
    {
        // Pass validation if the value itself is empty (let 'required' handle empty checks)
        if ($value === null || $value === '') {
            return true;
        }

        $compareValue = null;

        // 1. Check if the parameter points to another field in the request data
        if (isset($data[$this->compareField])) {
            $compareValue = $data[$this->compareField];
        }
        // 2. Fallback: Check if the parameter is a valid date string/keyword itself (e.g., 'now', 'today')
        elseif (strtotime($this->compareField) !== false) {
            $compareValue = $this->compareField;
        }
        // 3. If it's neither a data field nor a valid date phrase, fail validation
        else {
            return false;
        }

        // If the targeted field value is empty, skip validation
        if ($compareValue === null || $compareValue === '') {
            return true;
        }

        $valueTime = strtotime($value);
        $compareTime = strtotime($compareValue);

        if ($valueTime === false || $compareTime === false) {
            return false;
        }

        return $valueTime > $compareTime;
    }

    protected function getDefaultMessage(): string
    {
        return "This date must be after {$this->compareField}";
    }
}