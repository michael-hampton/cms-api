<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\Database;

trait RelationshipBuilder
{
    private function hasOne(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null,
        bool $returnRelation = false
    )
    {
        $relationData = [
            'type' => 'hasOne',
            'related' => $related,
            'foreign_key' => $foreignKey ?: strtolower($this->getClassName()) . '_id',
            'local_key' => $localKey ?: 'id'
        ];

        $handler = new HasOneHandler($this->database, $relationData, $returnRelation);
        $handler->setContext($this, $relationData);

        if ($returnRelation) {
            return $handler->newQuery();
        }

        // Otherwise, load the actual relation data
        return $handler->loadForSingleModel($this, $relationData);

    }

    private function hasMany(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null,
        bool $returnRelation = false
    )
    {
        $relationData = [
            'type' => 'hasMany',
            'related' => $related,
            'foreign_key' => $foreignKey ?: strtolower($this->getClassName()) . '_id',
            'local_key' => $localKey ?: 'id'
        ];

        $handler = new HasManyHandler(Database::getInstance(), $relationData, $returnRelation);
        $handler->setContext($this, $relationData);

        if ($returnRelation) {
            return $handler->newQuery();
        }

        return $handler->loadForSingleModel($this, $relationData);
    }

    private function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null,
        bool $returnRelation = false
    )
    {
        $relationData = [
            'type' => 'belongsTo',
            'related' => $related,
            'foreign_key' => $foreignKey ?: strtolower($this->getClassName($related)) . '_id',
            'owner_key' => $ownerKey ?: 'id'
        ];

        $handler = new BelongsToHandler($this->database, $relationData, $returnRelation);
        $handler->setContext($this, $relationData);

        if ($returnRelation) {
            return $handler->newQuery();
        }

        return $handler->loadForSingleModel($this, $relationData);
    }

    private function belongsToMany(
        string  $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        bool $returnRelation = false
    )
    {
        $relationData = [
            'type' => 'belongsToMany',
            'related' => $related,
            'pivot_table' => $table ?: $this->guessPivotTable(get_class($this), $related),
            'foreign_key' => $foreignPivotKey ?: strtolower($this->getClassName()) . '_id',
            'related_key' => $relatedPivotKey ?: strtolower($this->getClassName($related)) . '_id'
        ];

        $handler = new BelongsToManyHandler($this->database, $relationData, $returnRelation);
        $handler->setContext($this, $relationData);

        if ($returnRelation) {
            return $handler->newQuery();
        }

        return $handler->loadForSingleModel($this, $relationData);
    }

    /**
     * Guess pivot table name for belongsToMany relationships
     */
    private function guessPivotTable(string $model1, string $model2): string
    {
        $models = [
            strtolower(basename(str_replace('\\', '/', $model1))),
            strtolower(basename(str_replace('\\', '/', $model2)))
        ];
        sort($models);
        return implode('_', $models);
    }
}