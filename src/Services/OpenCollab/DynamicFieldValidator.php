<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\CustomFieldDefinition;

/**
 * Validates a map of submitted values against a collection of CustomFieldDefinition records.
 *
 * This is a pure collaborator with no side effects — it only returns a validation
 * errors array. Empty array means all values are valid.
 *
 * Used by:
 *   - ContributorProfileService  (profile/settings and onboarding saves)
 *   - ContributorRequestService  (contributor request form submission)
 */
final class DynamicFieldValidator
{
    /**
     * Validates submitted values against the provided field definitions.
     *
     * @param Collection<int, CustomFieldDefinition> $definitions
     * @param array<string, mixed>                   $submitted  key => raw value
     *
     * @return array<string, string>  key => error message; empty if all valid
     */
    public function validate(Collection $definitions, array $submitted): array
    {
        $errors = [];

        foreach ($definitions->all() as $definition) {
            $key   = (string) $definition->key;
            $value = $submitted[$key] ?? null;

            $error = $this->validateField($definition, $value);

            if ($error !== null) {
                $errors[$key] = $error;
            }
        }

        return $errors;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function validateField(CustomFieldDefinition $definition, mixed $value): ?string
    {
        $isEmpty = $this->isEmpty($value);

        if ($definition->is_required && $isEmpty) {
            $label = $definition->name ?: $definition->key;
            return "{$label} is required.";
        }

        // Optional fields with no value pass all further checks.
        if ($isEmpty) {
            return null;
        }

        return match ($definition->type) {
            'email'        => $this->validateEmail($definition, $value),
            'url'          => $this->validateUrl($definition, $value),
            'number'       => $this->validateNumber($definition, $value),
            'select'       => $this->validateSelect($definition, $value),
            'multi_select' => $this->validateMultiSelect($definition, $value),
            default        => null,
        };
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    private function validateEmail(CustomFieldDefinition $definition, mixed $value): ?string
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $label = $definition->name ?: $definition->key;
            return "{$label} must be a valid email address.";
        }

        return null;
    }

    private function validateUrl(CustomFieldDefinition $definition, mixed $value): ?string
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $label = $definition->name ?: $definition->key;
            return "{$label} must be a valid URL.";
        }

        return null;
    }

    private function validateNumber(CustomFieldDefinition $definition, mixed $value): ?string
    {
        if (!is_numeric($value)) {
            $label = $definition->name ?: $definition->key;
            return "{$label} must be a number.";
        }

        return null;
    }

    private function validateSelect(CustomFieldDefinition $definition, mixed $value): ?string
    {
        $options       = $definition->getOptionsAttribute() ?? [];
        $allowedValues = array_column($options, 'value');

        if (!in_array($value, $allowedValues, true)) {
            $label = $definition->name ?: $definition->key;
            return "{$label} is not a valid option.";
        }

        return null;
    }

    private function validateMultiSelect(CustomFieldDefinition $definition, mixed $value): ?string
    {
        if (!is_array($value)) {
            $label = $definition->name ?: $definition->key;
            return "{$label} must be an array of selected values.";
        }

        $options       = $definition->getOptionsAttribute() ?? [];
        $allowedValues = array_column($options, 'value');

        foreach ($value as $item) {
            if (!in_array($item, $allowedValues, true)) {
                $label = $definition->name ?: $definition->key;
                return "{$label} contains an invalid option.";
            }
        }

        return null;
    }
}