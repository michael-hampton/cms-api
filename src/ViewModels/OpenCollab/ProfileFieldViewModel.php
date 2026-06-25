<?php

namespace App\ViewModels\OpenCollab;

use App\Models\CustomFieldDefinition;

final readonly class ProfileFieldViewModel
{
    /**
     * @param array<int, array{label: string, value: string}> $options
     * @param array<int, string> $selectedValues
     * @param array<int, string> $listValues
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $type,
        public string $renderType,
        public string $description,
        public string $placeholder,
        public bool $required,
        public mixed $value,
        public string $stringValue,
        public array $options = [],
        public array $selectedValues = [],
        public array $listValues = [],
    ) {}

    public static function fromDefinition(CustomFieldDefinition $definition, mixed $profile): self
    {
        $key = (string) $definition->key;
        $type = (string) ($definition->type ?? 'text');

        $rawValue = self::resolveValue($definition, $profile);
        $stringValue = is_scalar($rawValue) || $rawValue === null
            ? (string) ($rawValue ?? '')
            : json_encode($rawValue);

        return new self(
            key: $key,
            name: (string) ($definition->name ?: ucfirst(str_replace('_', ' ', $key))),
            type: $type,
            renderType: self::resolveRenderType($key, $type),
            description: (string) ($definition->description ?? ''),
            placeholder: (string) ($definition->placeholder ?? ''),
            required: (bool) ($definition->is_required ?? false),
            value: $rawValue,
            stringValue: $stringValue,
            options: self::normaliseOptions($definition->options ?? []),
            selectedValues: self::normaliseValueList($rawValue),
            listValues: self::normaliseValueList($rawValue),
        );
    }

    public function errorId(): string
    {
        return "{$this->key}-error";
    }

    public function existingInputName(): string
    {
        return "{$this->key}_existing";
    }

    public function previewId(): string
    {
        return "{$this->key}-preview";
    }

    public function placeholderId(): string
    {
        return "{$this->key}-placeholder";
    }

    public function inputType(): string
    {
        return in_array($this->type, ['email', 'url'], true)
            ? $this->type
            : 'text';
    }

    public function isSelected(string $value): bool
    {
        return in_array($value, $this->selectedValues, true);
    }

    private static function resolveValue(CustomFieldDefinition $definition, mixed $profile): mixed
    {
        if (!$profile) {
            return $definition->default_value ?? '';
        }

        $column = $definition->profile_column ?: $definition->key;

        return $profile->{$column} ?? $definition->default_value ?? '';
    }

    private static function resolveRenderType(string $key, string $type): string
    {
        if ($key === 'avatar' && in_array($type, ['file', 'image'], true)) {
            return 'image';
        }

        if ($type === 'textarea') {
            return 'textarea';
        }

        if ($type === 'select') {
            return 'select';
        }

        if ($type === 'image') {
            return 'image';
        }

        if ($type === 'multi_select') {
            return 'multi_select';
        }

        if ($key === 'writing_samples' || $type === 'json') {
            return 'list';
        }

        return 'input';
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function normaliseOptions(mixed $options): array
    {
        if (is_string($options) && trim($options) !== '') {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($options)) {
            return [];
        }

        return array_values(array_map(static function ($option): array {
            if (is_array($option)) {
                $label = (string) ($option['label'] ?? $option['value'] ?? '');
                $value = (string) ($option['value'] ?? $label);

                return [
                    'label' => $label,
                    'value' => $value,
                ];
            }

            return [
                'label' => (string) $option,
                'value' => (string) $option,
            ];
        }, $options));
    }

    /**
     * @return array<int, string>
     */
    private static function normaliseValueList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static function ($item): string {
                if (is_array($item)) {
                    return trim((string) ($item['url'] ?? ''));
                }

                return trim((string) $item);
            }, $value)));
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return self::normaliseValueList($decoded);
            }

            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
    }

    public function sampleLinks(): array
    {
        if (!$this->stringValue) {
            return [
                ['url' => '', 'title' => ''],
                ['url' => '', 'title' => ''],
                ['url' => '', 'title' => ''],
            ];
        }

        $decoded = json_decode($this->stringValue, true);

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $samples = [];

        foreach ($decoded as $item) {
            $samples[] = [
                'url'   => (string) ($item['url'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
            ];
        }

        while (count($samples) < 3) {
            $samples[] = ['url' => '', 'title' => ''];
        }

        return array_slice($samples, 0, 3);
    }
}
