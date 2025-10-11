<?php

namespace App\Framework\Validation\Rules;

use App\Framework\Database\Database;

class RequiredWithRule extends BaseValidationRule
{
    protected $parameters = [];
    protected Database $database;

    public function __construct(?Database $database = null)
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function validate($value, array $data = []): bool
    {
        // Check if any of the other fields are present
        foreach ($this->parameters as $field) {
            $fieldValue = $this->getNestedValue($data, $field);
            if (!$this->isEmpty($fieldValue)) {
                // If other field is present, this field must also be present
                return !$this->isEmpty($value);
            }
        }

        // If none of the other fields are present, this field is optional
        return true;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function setDatabase(Database $database): void
    {
        $this->database = $database;
    }

    private function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    private function getNestedValue(array $data, string $key)
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

    protected function getDefaultMessage(): string
    {
        $fields = implode(', ', $this->parameters);
        return "This field is required when {$fields} is present.";
    }
}