<?php

namespace App\Framework\Tests\Factories;

use App\Models\Model;

abstract class Factory
{
    protected array $attributes = [];
    protected string $model;

    public function __construct()
    {
        $this->attributes = $this->definition();
    }

    abstract protected function definition(): array;
    abstract protected function model(): string;

    public static function new(): static
    {
        return new static();
    }

    public function state(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    public function set(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function create(array $overrides = []): Model
    {
        $modelClass = $this->model();
        $attributes = array_merge($this->attributes, $overrides);

        return $modelClass::create($attributes);
    }

    public function count(int $count): FactoryCollection
    {
        return new FactoryCollection($this, $count);
    }

    public function make(array $overrides = []): Model
    {
        $modelClass = $this->model();
        $attributes = array_merge($this->attributes, $overrides);

        return new $modelClass($attributes);
    }

    public function raw(array $overrides = []): array
    {
        return array_merge($this->attributes, $overrides);
    }
}