<?php

namespace App\Framework\Support;

use App\Models\Model;

trait Serializable
{
    /**
     * Convert the model to its array representation
     */
    public function toArray(): array
    {
        $attributes = $this->getArrayableAttributes();
        $relations = $this->getArrayableRelations();

        return array_merge($attributes, $relations);
    }

    /**
     * Get the model's attributes in array form
     */
    protected function getArrayableAttributes(): array
    {
        return $this->getAttributesForSerialization();
    }

    /**
     * Get the model's relationships in array form
     */
    protected function getArrayableRelations(): array
    {
        $relations = [];

        // Include eager loaded relations
        foreach ($this->eagerLoaded as $key => $value) {
            if ($this->shouldIncludeRelation($key)) {
                $relations[$key] = $this->serializeRelation($value);
            }
        }

        // Include manually loaded relations
        foreach ($this->relations as $key => $value) {
            if ($this->shouldIncludeRelation($key) && !array_key_exists($key, $relations)) {
                $relations[$key] = $this->serializeRelation($value);
            }
        }

        return $relations;
    }

    /**
     * Determine if a relation should be included in serialization
     */
    protected function shouldIncludeRelation(string $key): bool
    {
        // You can override this in specific models to control which relations are included
        return true;
    }

    /**
     * Serialize a relation value
     */
    protected function serializeRelation($value)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Collection) {
            return $value->toArray();
        }

        if ($value instanceof Model) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(function($item) {
                return $this->serializeRelation($item);
            }, $value);
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Get attributes for serialization, applying any attribute casting
     */
    protected function getAttributesForSerialization(): array
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            $attributes[$key] = $this->getAttributeForSerialization($key, $value);
        }

        return $attributes;
    }

    /**
     * Get a single attribute for serialization
     */
    protected function getAttributeForSerialization(string $key, $value)
    {
        // Apply any attribute mutators
        $mutatorMethod = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Attribute';
        if (method_exists($this, $mutatorMethod)) {
            return $this->$mutatorMethod($value);
        }

        // Apply casting
        return $this->castAttribute($key, $value);
    }

    /**
     * Convert model to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Make the model JSON serializable
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}