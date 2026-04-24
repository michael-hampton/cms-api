<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\Database;

trait RelationshipBuilder
{
    protected function hasOne(
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

    protected function hasMany(
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

    protected function belongsTo(
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

    protected function belongsToMany(
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

    // Add to src/Models/Model.php

    /**
     * Define a polymorphic one-to-many relationship
     */
    protected function morphMany(
        string  $related,
        string  $name,
        ?string $type = null,
        ?string $id = null,
        ?string $localKey = null,
        bool    $returnRelation = false
    )
    {
        $type = $type ?: $name . '_type';
        $id = $id ?: $name . '_id';
        $localKey = $localKey ?: 'id';

        $relationData = [
            'type' => 'morphMany',
            'related' => $related,
            'morph_type' => $type,
            'morph_id' => $id,
            'local_key' => $localKey
        ];

        $handler = new MorphManyHandler($this->database, $relationData, $returnRelation);
        $handler->setContext($this, $relationData);

        if ($returnRelation) {
            return $handler->newQuery();
        }

        return $handler->loadForSingleModel($this, $relationData);
    }

    /**
     * Define a polymorphic one-to-one relationship
     */
    protected function morphOne(
        string  $related,
        string  $name,
        ?string $type = null,
        ?string $id = null,
        ?string $localKey = null,
        bool    $returnRelation = false
    )
    {
        $type = $type ?: $name . '_type';
        $id = $id ?: $name . '_id';
        $localKey = $localKey ?: 'id';

        $relationData = [
            'type' => 'morphOne',
            'related' => $related,
            'morph_type' => $type,
            'morph_id' => $id,
            'local_key' => $localKey
        ];

        $handler = new MorphOneHandler($this->database, $relationData, $returnRelation);
        $handler->setContext($this, $relationData);

        if ($returnRelation) {
            return $handler->newQuery();
        }

        return $handler->loadForSingleModel($this, $relationData);
    }

    /**
     * Define an inverse polymorphic relationship
     */
    protected function morphTo(
        ?string $name = null,
        ?string $type = null,
        ?string $id = null,
        ?string $ownerKey = null,
        bool    $returnRelation = false
    )
    {
        // Guess the name from the calling method if not provided
        if ($name === null) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $name = $backtrace[1]['function'];
        }

        $type = $type ?: $name . '_type';
        $id = $id ?: $name . '_id';
        $ownerKey = $ownerKey ?: 'id';

        $relationData = [
            'type' => 'morphTo',
            'morph_type' => $type,
            'morph_id' => $id,
            'owner_key' => $ownerKey
        ];

        $handler = new MorphToHandler($this->database, $relationData, $returnRelation);
        $handler->setContext($this, $relationData);

        if ($returnRelation) {
            return $handler->newQuery();
        }

        return $handler->loadForSingleModel($this, $relationData);
    }
}