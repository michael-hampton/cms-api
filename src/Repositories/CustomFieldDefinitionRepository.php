<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\CustomFieldDefinition;
use App\Models\Model;

class CustomFieldDefinitionRepository extends Repository
{
    protected function getModelClass(): string
    {
        return CustomFieldDefinition::class;
    }

    public function findByKey(string $key): ?CustomFieldDefinition
    {
        return CustomFieldDefinition::byKey($key)->first();
    }

    public function getActive(): Collection
    {
        return CustomFieldDefinition::active()->get();
    }

    public function getRequired(): Collection
    {
        return CustomFieldDefinition::ordered()->where('is_required', true)->where('is_active', 1)->get();
    }

    public function getSearchableFields(): Collection
    {
        return CustomFieldDefinition::ordered()->where('is_searchable', true)->where('is_active', true)->get();
    }

    public function getGroupedFields(): array
    {
        $fields = $this->getActive();
        $grouped = [];

        foreach ($fields as $field) {
            $group = $field->group_name ?: 'default';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $field;
        }

        return $grouped;
    }

    public function createField(array $data): Model
    {
        $data = $this->prepareFieldData($data);
        return $this->create($data);
    }

    public function getByType(string $type): array
    {
        return $this->where('type', $type)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getByGroup(string $group): array
    {
        return CustomFieldDefinition::byGroup($group)->active()->ordered()->get();
    }

    public function searchFields(string $query, int $limit = 10): array
    {
        return $this->where('name', 'LIKE', "%{$query}%")
            ->orWhere('key', 'LIKE', "%{$query}%")
            ->where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->limit($limit)
            ->get();
    }

    public function bulkUpdateSortOrder(array $fieldOrders): bool
    {
        $this->database->beginTransaction();

        try {
            foreach ($fieldOrders as $fieldId => $sortOrder) {
                $this->database->update(
                    'custom_field_definitions',
                    ['sort_order' => $sortOrder],
                    ['id' => $fieldId]
                );
            }

            $this->database->commit();
            return true;
        } catch (Exception $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    public function getFieldUsageStats(int $fieldId): array
    {
        $results = $this->database->select(
            "SELECT COUNT(*) as usage_count,
                COUNT(CASE WHEN pcf.field_value IS NOT NULL AND pcf.field_value != '' THEN 1 END) as filled_count
         FROM page_custom_fields pcf
         WHERE pcf.custom_field_definition_id = ?",
            [$fieldId]
        );

        return $results[0] ?? ['usage_count' => 0, 'filled_count' => 0];
    }

    public function cleanupUnusedFields(): int
    {
        $unusedFieldIds = $this->getUnusedFieldIds();

        if (empty($unusedFieldIds)) {
            return 0;
        }

        return $this->database->delete('custom_field_definitions', ['id' => $unusedFieldIds]);
    }

    private function generateKey(string $name): string
    {
        $key = strtolower(trim($name));
        $key = preg_replace('/[^a-z0-9_]/', '_', $key);
        $key = preg_replace('/_+/', '_', $key);
        $key = trim($key, '_');

        // Ensure uniqueness
        $originalKey = $key;
        $counter = 1;

        while ($this->findByKey($key)) {
            $key = $originalKey . '_' . $counter;
            $counter++;
        }

        return $key;
    }

    private function getNextSortOrder(): int
    {
        $result = $this->database->select(
            "SELECT MAX(sort_order) as max_order FROM custom_field_definitions"
        );

        return ($result[0]['max_order'] ?? 0) + 10;
    }

    private function prepareFieldData(array $data): array
    {
        // Auto-generate key if not provided
        if (empty($data['key'])) {
            $data['key'] = $this->generateUniqueKey($data['name']);
        }

        // Ensure JSON fields are properly encoded
        $jsonFields = ['options', 'validation_rules'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        // Set defaults
        $defaults = [
            'is_active' => true,
            'is_required' => false,
            'is_searchable' => false,
            'sort_order' => $this->getNextSortOrder()
        ];

        foreach ($defaults as $key => $defaultValue) {
            $data[$key] = $data[$key] ?? $defaultValue;
        }

        return $data;
    }

    private function groupFieldsByGroup(array $fields): array
    {
        $grouped = [];

        foreach ($fields as $field) {
            $group = $field->group_name ?: 'default';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $field;
        }

        return $grouped;
    }

    private function generateUniqueKey(string $name): string
    {
        $baseKey = $this->createKeyFromName($name);
        return $this->ensureUniqueKey($baseKey);
    }

    private function createKeyFromName(string $name): string
    {
        $key = strtolower(trim($name));
        $key = preg_replace('/[^a-z0-9_]/', '_', $key);
        $key = preg_replace('/_+/', '_', $key);
        return trim($key, '_');
    }

    private function ensureUniqueKey(string $baseKey): string
    {
        $key = $baseKey;
        $counter = 1;

        while ($this->findByKey($key)) {
            $key = $baseKey . '_' . $counter;
            $counter++;
        }

        return $key;
    }

    private function getUnusedFieldIds(): array
    {
        $unusedFields = $this->database->select(
            "SELECT cfd.id FROM custom_field_definitions cfd
             LEFT JOIN page_custom_fields pcf ON cfd.id = pcf.custom_field_definition_id
             WHERE pcf.custom_field_definition_id IS NULL AND cfd.is_required = 0"
        );

        return array_column($unusedFields, 'id');
    }
}