<?php

namespace App\Repositories\Cms;

use App\Framework\Support\Collection;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Repositories\Repository;

class PageCustomFieldRepository extends Repository
{
    private $customFieldDefinitionRepository;

    public function __construct()
    {
        parent::__construct();
        $this->customFieldDefinitionRepository = new CustomFieldDefinitionRepository();
        $this->withoutSiteFilter();
    }

    protected function getModelClass(): string
    {
        return PageCustomField::class;
    }

    public function syncCustomFields(int $pageId, array $fields, int $siteId): void
    {
        // Use Model events to handle the sync
        $page = Page::find($pageId);
        if ($page) {
            $page->syncCustomFields($fields, $siteId); // This would trigger model events
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

    public function getCustomFieldsForPage(int $pageId): Collection
    {
        return PageCustomField::where('page_id', $pageId)->get();
    }

    public function getCustomFieldsByKeys(array $keys): Collection
    {
        return CustomFieldDefinition::whereIn('key', $keys)->get();
    }

}