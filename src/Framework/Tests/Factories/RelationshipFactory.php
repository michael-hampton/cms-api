<?php

namespace App\Framework\Tests\Factories;

use App\Models\CustomFieldDefinition;
use App\Models\Model;
use App\Models\RegionSet;

class RelationshipFactory
{
    /**
     * Attach a relationship
     */
    public static function attach(Model $parent, Model $child, string $pivotModel, array $additionalData = []): Model
    {
        $parentKey = static::getForeignKey(get_class($parent));
        $childKey = static::getForeignKey(get_class($child));

        $data = array_merge([
            $parentKey => $parent->id,
            $childKey => $child->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $additionalData);

        return $pivotModel::create($data);
    }

    /**
     * Get foreign key - exact same logic as RelationshipAnalyzer::getForeignKey
     */
    protected static function getForeignKey(string $modelClass): string
    {
        $customForeginKey = static::hasCustomForeignKey($modelClass);
        if ($customForeginKey) {
            return $customForeginKey;
        }

        $className = basename(str_replace('\\', '/', $modelClass));
        return strtolower($className) . '_id';
    }

    protected static function hasCustomForeignKey(string $modelClass): string
    {
        return match ($modelClass) {
            RegionSet::class => 'region_set_id',
            CustomFieldDefinition::class => 'custom_field_definition_id',
            default => ''
        };
    }
}