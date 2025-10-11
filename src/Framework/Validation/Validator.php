<?php

namespace App\Framework\Validation;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Validation\Rules\BetweenRule;
use App\Framework\Validation\Rules\ConfirmedRule;
use App\Framework\Validation\Rules\DateRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\IntegerRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\NumericRule;
use App\Framework\Validation\Rules\RegexRule;
use App\Framework\Validation\Rules\RequiredIfRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\RequiredWithRule;
use App\Framework\Validation\Rules\SometimesRule;
use App\Framework\Validation\Rules\UniqueRule;
use App\Framework\Validation\Rules\UrlRule;
use Exception;

class Validator
{
    private $database;
    private $customRules = [];

    private Collection $errors;

    public function __construct(?Database $database = null)
    {
        $this->database = $database ?: Database::getInstance();
        $this->errors = new Collection([]);
    }

    public function validate(array $data, array $rules): ValidationResult
    {
        $failedFields = [];
        $this->errors = new Collection([]);

        foreach ($rules as $field => $fieldRules) {
            $fieldErrors = $this->validateField($field, $data, $fieldRules);

            if (!empty($fieldErrors)) {
                $this->errors = $this->errors->merge($fieldErrors);
                $failedFields[] = $field;
            }
        }

        return new ValidationResult($this->errors->isEmpty(), $this->errors->all(), $failedFields);
    }

    private function validateField(string $field, array $data, $rules): array
    {
        $errors = [];
        $rules = is_array($rules) ? $rules : [$rules];

        // Handle wildcard fields (e.g., 'items.*', 'users.*.email')
        if (str_contains($field, '*')) {
            return $this->validateWildcardField($field, $data, $rules);
        }

        // Handle nested fields (e.g., 'user.email', 'settings.notification.email')
        $value = $this->getNestedValue($data, $field);

        // Check if field is required
        $isRequired = $this->hasRequiredRule($rules);
        $isEmpty = $this->isEmpty($value);

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $rule = $this->parseStringRule($rule);
            }

            if ($rule instanceof ConfirmedRule) {
                $rule->setField($field);
            }

            // Skip validation for non-required empty fields, except for required rule itself
            if ($isEmpty && !$isRequired && !$this->isRequiredRule($rule)) {
                continue;
            }

            if (!$rule->validate($value, $data)) {
                $errors[$field] = $rule->getMessage();
                break; // Stop at first error for this field
            }
        }

        return $errors;
    }

    private function hasRequiredRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $rule = $this->parseStringRule($rule);
            }

            if ($this->isRequiredRule($rule)) {
                return true;
            }
        }

        return false;
    }

    private function isRequiredRule($rule): bool
    {
        if (is_string($rule)) {
            return strpos($rule, 'required') === 0;
        }

        return $rule instanceof RequiredRule ||
            $rule instanceof RequiredIfRule;
    }

    private function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    private function validateWildcardField(string $field, array $data, array $rules): array
    {
        $errors = [];
        $expandedFields = $this->expandWildcardField($field, $data);

        foreach ($expandedFields as $expandedField) {
            $fieldErrors = $this->validateField($expandedField, $data, $rules);
            $errors = array_merge($errors, $fieldErrors);
        }

        return $errors;
    }

    private function expandWildcardField(string $field, array $data): array
    {
        $fields = [];
        $parts = explode('.', $field);

        $this->expandWildcardFieldRecursive($parts, $data, '', $fields);

        return $fields;
    }

    private function expandWildcardFieldRecursive(array $parts, array $data, string $prefix, array &$fields): void
    {
        if (empty($parts)) {
            return;
        }

        $part = array_shift($parts);

        if ($part === '*') {
            foreach (array_keys($data) as $key) {
                $newPrefix = $prefix ? "{$prefix}.{$key}" : $key;

                if (empty($parts)) {
                    $fields[] = $newPrefix;
                } else {
                    $nextData = $data[$key] ?? [];
                    if (is_array($nextData)) {
                        $this->expandWildcardFieldRecursive($parts, $nextData, $newPrefix, $fields);
                    }
                }
            }
        } else {
            $newPrefix = $prefix ? "{$prefix}.{$part}" : $part;
            $nextData = $data[$part] ?? [];

            if (empty($parts)) {
                $fields[] = $newPrefix;
            } elseif (is_array($nextData)) {
                $this->expandWildcardFieldRecursive($parts, $nextData, $newPrefix, $fields);
            }
        }
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

    private function parseStringRule(string $rule): ValidationRuleInterface
    {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        $ruleClass = $this->getRuleClass($ruleName);
        $ruleInstance = new $ruleClass($this->database);
        $ruleInstance->setParameters($parameters);

        return $ruleInstance;
    }

    public function extend(string $ruleName, ValidationRuleInterface $rule): void
    {
        $this->customRules[$ruleName] = $rule;
    }

    public function extendImplicit(string $ruleName, ValidationRuleInterface $rule): void
    {
        $this->customRules[$ruleName] = $rule;
    }

    private function getRuleClass(string $ruleName): string
    {
        // Check custom rules first
        if (isset($this->customRules[$ruleName])) {
            return get_class($this->customRules[$ruleName]);
        }

        $ruleMap = [
            'required' => RequiredRule::class,
            'required_if' => RequiredIfRule::class,
            'required_with' => RequiredWithRule::class,
            'unique' => UniqueRule::class,
            'max' => MaxLengthRule::class,
            'min' => MinLengthRule::class,
            'email' => EmailRule::class,
            'url' => UrlRule::class,
            'numeric' => NumericRule::class,
            'nullable' => NumericRule::class,
            'integer' => IntegerRule::class,
            'in' => InRule::class,
            'between' => BetweenRule::class,
            'regex' => RegexRule::class,
            'min_rule' => MinRule::class,
            'max_rule' => MaxRule::class,
            'min_length_rule' => MinLengthRule::class,
            'max_length_rule' => MaxLengthRule::class,
            'date_rule' => DateRule::class,
            'sometimes' => SometimesRule::class,  // ADD THIS
            'confirmed' => ConfirmedRule::class,
        ];

        if (!isset($ruleMap[$ruleName])) {
            throw new Exception("Unknown validation rule: {$ruleName}");
        }

        return $ruleMap[$ruleName];
    }

    public function errorCollection(): Collection
    {
        return $this->errors;
    }
}