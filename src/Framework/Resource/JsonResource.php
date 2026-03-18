<?php

namespace App\Framework\Resource;

use App\Framework\Http\Response;

abstract class JsonResource
{
    protected $resource;

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    abstract public function toArray(): array;

    public function toResponse(): Response
    {
        return Response::json($this->toArray());
    }

    public static function make($resource): self
    {
        return new static($resource);
    }

    public static function collection($resources): ResourceCollection
    {
        return new ResourceCollection($resources, static::class);
    }

    protected function when(bool $condition, $value, $default = null)
    {
        return $condition ? $value : $default;
    }

    protected function whenLoaded(string $relationship, $value = null, $default = null)
    {
        $relation = null;

        if (is_array($this->resource) && array_key_exists($relationship, $this->resource)) {
            $relation = $this->resource[$relationship];
        } elseif (is_object($this->resource)) {
            try {
                $val = $this->resource->{$relationship};
                if ($val !== null) {
                    $relation = $val;
                }
            } catch (\Throwable $e) {
                // relation not accessible
            }
        }

        if ($relation === null) {
            return $default;
        }

        $relation = is_array($relation) ? collect($relation) : $relation;

        if (is_callable($value)) {
            return $value($relation);
        }

        return $relation;
    }

    /**
     * Get an attribute from the resource (works with both arrays and objects)
     * Supports dot notation for nested attributes
     */
    protected function getAttribute(string $key, $default = null)
    {
        // Handle dot notation
        if (strpos($key, '.') !== false) {
            return $this->getNestedAttribute($key, $default);
        }

        if (is_array($this->resource)) {
            return $this->resource[$key] ?? $default;
        }

        if (is_object($this->resource)) {
            return $this->resource->{$key} ?? $default;
        }

        return $default;
    }

    /**
     * Get nested attribute using dot notation
     */
    private function getNestedAttribute(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->resource;

        foreach ($keys as $segment) {
            if (is_array($value) && isset($value[$segment])) {
                $value = $value[$segment];
            } elseif (is_object($value) && $value->{$segment}) {
                $value = $value->{$segment};
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Magic method to access attributes directly
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic method to check if attribute exists
     */
    public function __isset(string $key): bool
    {
        return $this->getAttribute($key) !== null;
    }

    /**
     * Get all resource data as array
     */
    protected function getAllAttributes(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (is_object($this->resource)) {
            return method_exists($this->resource, 'toArray')
                ? $this->resource->toArray()
                : (array)$this->resource;
        }

        return [];
    }
}