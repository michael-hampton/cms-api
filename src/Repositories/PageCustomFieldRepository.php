<?php

namespace App\Repositories;

use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;

class PageCustomFieldRepository extends Repository
{
    private $customFieldDefinitionRepository;

    public function __construct()
    {
        parent::__construct();
        $this->customFieldDefinitionRepository = new CustomFieldDefinitionRepository();
    }

    protected function getModelClass(): string
    {
        return PageCustomField::class;
    }

    public function syncCustomFields(int $pageId, array $fields): void
    {
        // Use Model events to handle the sync
        $page = Page::find($pageId);
        if ($page) {
            $page->syncCustomFields($fields); // This would trigger model events
        }
    }

    public function getPageCustomFields(int $pageId): array
    {
        $results = $this->database->select(
            "SELECT pcf.*, cfd.name, cfd.key, cfd.type, cfd.description, 
                    cfd.is_required, cfd.options, cfd.group_name
             FROM page_custom_fields pcf 
             INNER JOIN custom_field_definitions cfd ON pcf.custom_field_definition_id = cfd.id 
             WHERE pcf.page_id = ? AND cfd.is_active = 1
             ORDER BY cfd.sort_order ASC, cfd.name ASC",
            [$pageId]
        );

        if (empty($results)) {
            return [];
        }

        $models = [];
        foreach ($results as $data) {
            $model = new PageCustomField($data);
            $model->exists = true;
            $model->original = $model->attributes;

            // Add field definition data for easier access
            $model->field_name = $data['name'];
            $model->field_key = $data['key'];
            $model->field_type = $data['type'];
            $model->field_description = $data['description'];
            $model->is_required = $data['is_required'];
            $model->field_options = json_decode($data['options'] ?? '[]', true);
            $model->group_name = $data['group_name'];

            $models[] = $model;
        }

        return $models;
    }

    public function getPageCustomFieldsGrouped(int $pageId): array
    {
        $fields = $this->getPageCustomFields($pageId);
        $grouped = [];

        foreach ($fields as $field) {
            $group = $field->group_name ?: 'default';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = [
                'key' => $field->field_key,
                'name' => $field->field_name,
                'type' => $field->field_type,
                'value' => $field->getTypedValue(),
                'raw_value' => $field->field_value,
                'description' => $field->field_description,
                'is_required' => $field->is_required,
                'options' => $field->field_options
            ];
        }

        return $grouped;
    }

    public function getPageCustomFieldValue(int $pageId, string $fieldKey)
    {
        $results = $this->database->select(
            "SELECT pcf.field_value, cfd.type 
             FROM page_custom_fields pcf 
             INNER JOIN custom_field_definitions cfd ON pcf.custom_field_definition_id = cfd.id 
             WHERE pcf.page_id = ? AND cfd.key = ? AND cfd.is_active = 1",
            [$pageId, $fieldKey]
        );

        if (empty($results)) {
            return null;
        }

        $field = $results[0];
        $definition = new CustomFieldDefinition(['type' => $field['type']]);
        return $definition->getFormattedValue($field['field_value']);
    }

    public function searchPagesByCustomField(string $fieldKey, $value, string $operator = '='): array
    {
        $sql = "SELECT DISTINCT p.* FROM pages p 
                INNER JOIN page_custom_fields pcf ON p.id = pcf.page_id 
                INNER JOIN custom_field_definitions cfd ON pcf.custom_field_definition_id = cfd.id 
                WHERE cfd.key = ? AND cfd.is_searchable = 1";

        $params = [$fieldKey];

        switch ($operator) {
            case 'LIKE':
                $sql .= " AND pcf.field_value LIKE ?";
                $params[] = "%{$value}%";
                break;
            case '>':
            case '<':
            case '>=':
            case '<=':
                $sql .= " AND CAST(pcf.field_value AS DECIMAL) {$operator} ?";
                $params[] = $value;
                break;
            default:
                $sql .= " AND pcf.field_value = ?";
                $params[] = $value;
        }

        $sql .= " ORDER BY p.created_at DESC";

        $results = $this->database->select($sql, $params);

        $models = [];
        foreach ($results as $data) {
            $model = new \App\Models\Page($data);
            $model->exists = true;
            $model->original = $model->attributes;
            $models[] = $model;
        }

        return $models;
    }
}