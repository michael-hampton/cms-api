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
        if ($value === null || $value === '') {
            return true;
        }

        if (!isset($data[$this->compareField])) {
            return false;
        }

        $compareValue = $data[$this->compareField];

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