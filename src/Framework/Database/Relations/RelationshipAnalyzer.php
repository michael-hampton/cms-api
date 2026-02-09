<?php

namespace App\Framework\Database\Relations;

use Exception;
use ReflectionMethod;

class RelationshipAnalyzer
{
    public function analyzeRelationshipMethod($model, string $relation): array
    {
        try {
            $reflection = new ReflectionMethod($model, $relation);
            $filename = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if ($filename && $startLine && $endLine) {
                $source = implode("", array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));

                // Parse belongsToMany
                if (preg_match('/\$this->belongsToMany\s*\(\s*([^,\)]+)(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?/', $source, $matches)) {
                    $relatedClass = $this->normalizeClassName(trim($matches[1], '"\''), $model);
                    $pivotTable = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : $this->guessPivotTable(get_class($model), $relatedClass);
                    $foreignKey = isset($matches[3]) && !empty($matches[3]) ? $matches[3] : $this->getForeignKey(get_class($model));
                    $relatedKey = isset($matches[4]) && !empty($matches[4]) ? $matches[4] : $this->getForeignKey($relatedClass);

                    return [
                        'type' => 'belongsToMany',
                        'related' => $relatedClass,
                        'pivot_table' => $pivotTable,
                        'foreign_key' => $foreignKey,
                        'related_key' => $relatedKey
                    ];
                }

                // Parse hasMany
                if (preg_match('/\$this->hasMany\s*\(\s*([^,\)]+)(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?/', $source, $matches)) {
                    $relatedClass = $this->normalizeClassName(trim($matches[1], '"\''), $model);
                    $foreignKey = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : $this->getForeignKey(get_class($model));
                    $localKey = isset($matches[3]) && !empty($matches[3]) ? $matches[3] : 'id';

                    return [
                        'type' => 'hasMany',
                        'related' => $relatedClass,
                        'foreign_key' => $foreignKey,
                        'local_key' => $localKey
                    ];
                }

                // Parse hasOne
                if (preg_match('/\$this->hasOne\s*\(\s*([^,\)]+)(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?/', $source, $matches)) {
                    $relatedClass = $this->normalizeClassName(trim($matches[1], '"\''), $model);
                    $foreignKey = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : $this->getForeignKey(get_class($model));
                    $localKey = isset($matches[3]) && !empty($matches[3]) ? $matches[3] : 'id';

                    return [
                        'type' => 'hasOne',
                        'related' => $relatedClass,
                        'foreign_key' => $foreignKey,
                        'local_key' => $localKey
                    ];
                }

                // Parse belongsTo
                if (preg_match('/\$this->belongsTo\s*\(\s*([^,\)]+)(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?/', $source, $matches)) {
                    $relatedClass = $this->normalizeClassName(trim($matches[1], '"\''), $model);
                    $foreignKey = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : $relation . '_id';
                    $ownerKey = isset($matches[3]) && !empty($matches[3]) ? $matches[3] : 'id';

                    return [
                        'type' => 'belongsTo',
                        'related' => $relatedClass,
                        'foreign_key' => $foreignKey,
                        'owner_key' => $ownerKey
                    ];
                }

                // Parse morphMany
                if (preg_match('/\$this->morphMany\s*\(\s*([^,\)]+)\s*,\s*[\'"]([^\'",\)]+)[\'"](?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?/', $source, $matches)) {
                    $relatedClass = $this->normalizeClassName(trim($matches[1], '"\''), $model);
                    $name = $matches[2];
                    $type = isset($matches[3]) && !empty($matches[3]) ? $matches[3] : $name . '_type';
                    $id = isset($matches[4]) && !empty($matches[4]) ? $matches[4] : $name . '_id';
                    $localKey = isset($matches[5]) && !empty($matches[5]) ? $matches[5] : 'id';

                    return [
                        'type' => 'morphMany',
                        'related' => $relatedClass,
                        'morph_type' => $type,
                        'morph_id' => $id,
                        'local_key' => $localKey,
                        'parent_class' => get_class($model),
                    ];
                }

                // Parse morphOne
                if (preg_match('/\$this->morphOne\s*\(\s*([^,\)]+)\s*,\s*[\'"]([^\'",\)]+)[\'"](?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?/', $source, $matches)) {
                    $relatedClass = $this->normalizeClassName(trim($matches[1], '"\''), $model);
                    $name = $matches[2];
                    $type = isset($matches[3]) && !empty($matches[3]) ? $matches[3] : $name . '_type';
                    $id = isset($matches[4]) && !empty($matches[4]) ? $matches[4] : $name . '_id';
                    $localKey = isset($matches[5]) && !empty($matches[5]) ? $matches[5] : 'id';

                    return [
                        'type' => 'morphOne',
                        'related' => $relatedClass,
                        'morph_type' => $type,
                        'morph_id' => $id,
                        'local_key' => $localKey,
                        'parent_class' => get_class($model),
                    ];
                }

                // Parse morphTo
                if (preg_match('/\$this->morphTo\s*\((?:\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?(?:\s*,\s*[\'"]([^\'",\)]+)[\'"])?/', $source, $matches)) {
                    $name = isset($matches[1]) && !empty($matches[1]) ? $matches[1] : $relation;
                    $type = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : $name . '_type';
                    $id = isset($matches[3]) && !empty($matches[3]) ? $matches[3] : $name . '_id';
                    $ownerKey = isset($matches[4]) && !empty($matches[4]) ? $matches[4] : 'id';

                    return [
                        'type' => 'morphTo',
                        'morph_type' => $type,
                        'morph_id' => $id,
                        'owner_key' => $ownerKey
                    ];
                }
            }
        } catch (Exception $e) {
            // Continue to fallback
        }

        // Fallback - guess relationship type
        $relatedClass = $this->guessModelClassFromRelation($relation);
        if (!str_contains($relatedClass, '\\')) {
            $relatedClass = 'App\\Models\\' . $relatedClass;
        }

        return [
            'type' => 'belongsTo',
            'related' => $relatedClass,
            'foreign_key' => $relation . '_id',
            'owner_key' => 'id'
        ];
    }

    private function normalizeClassName(string $className, $model): string
    {
        if (str_contains($className, '::class')) {
            $className = str_replace('::class', '', $className);
        }

        $className = trim($className);

        if (str_contains($className, '\\')) {
            return $className;
        }

        return 'App\\Models\\' . $className;
    }

    private function getForeignKey(string $modelClass): string
    {
        $className = basename(str_replace('\\', '/', $modelClass));
        return strtolower($className) . '_id';
    }

    private function guessPivotTable(string $model1, string $model2): string
    {
        $models = [
            strtolower(basename(str_replace('\\', '/', $model1))),
            strtolower(basename(str_replace('\\', '/', $model2)))
        ];
        sort($models);
        return implode('_', $models);
    }

    private function guessModelClassFromRelation(string $relation): string
    {
        $singularMap = [
            'categories' => 'Category',
            'stories' => 'Story',
            'countries' => 'Country',
            'companies' => 'Company',
            'cities' => 'City',
            'activities' => 'Activity',
            'properties' => 'Property',
            'queries' => 'Query',
            'galleries' => 'Gallery',
            'entries' => 'Entry',
            'accessories' => 'Accessory',
            'people' => 'Person',
            'children' => 'Child',
            'men' => 'Man',
            'women' => 'Woman',
            'teeth' => 'Tooth',
            'feet' => 'Foot',
            'geese' => 'Goose',
            'mice' => 'Mouse',
            'data' => 'Data',
        ];

        if (isset($singularMap[$relation])) {
            return $singularMap[$relation];
        }

        if (str_ends_with($relation, 's') && strlen($relation) > 1) {
            return ucfirst(rtrim($relation, 's'));
        }

        return ucfirst($relation);
    }
}