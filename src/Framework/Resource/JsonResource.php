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
        if (is_array($this->resource) && isset($this->resource[$relationship])) {
            return $value ?: $this->resource[$relationship];
        }

        if (is_object($this->resource) && property_exists($this->resource, $relationship)) {
            return $value ?: $this->resource->{$relationship};
        }

        return $default;
    }
}