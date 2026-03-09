<?php

namespace App\Framework\Validation\Rules;

class RequiredWithoutRule extends BaseValidationRule
{
    protected $parameters = [];

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function validate($value, array $data = []): bool
    {
        // If ALL of the listed fields are absent/empty, this field is required
        $allAbsent = true;

        foreach ($this->parameters as $field) {
            $fieldValue = $this->getNestedValue($data, $field);
            if (!$this->isEmpty($fieldValue)) {
                $allAbsent = false;
                break;
            }
        }

        if ($allAbsent) {
            // All other fields are absent — this field is required
            return !$this->isEmpty($value);
        }

        // At least one other field is present — this field is optional
        return true;
    }

    private function getNestedValue(array $data, string $key): mixed
    {
        if (!str_contains($key, '.')) {
            return $data[$key] ?? null;
        }

        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $nestedKey) {
            if (!is_array($value) || !array_key_exists($nestedKey, $value)) {
                return null;
            }
            $value = $value[$nestedKey];
        }

        return $value;
    }

    private function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    protected function getDefaultMessage(): string
    {
        $fields = implode(', ', $this->parameters);
        return "This field is required when {$fields} is not present.";
    }
}