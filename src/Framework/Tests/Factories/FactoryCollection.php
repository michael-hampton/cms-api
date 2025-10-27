<?php

namespace App\Framework\Tests\Factories;

class FactoryCollection
{
    protected Factory $factory;
    protected int $count;
    protected array $states = [];

    public function __construct(Factory $factory, int $count)
    {
        $this->factory = $factory;
        $this->count = $count;
    }

    /**
     * Apply state to all instances
     */
    public function state(array $attributes): static
    {
        $this->states = array_merge($this->states, $attributes);
        return $this;
    }

    /**
     * Create multiple instances
     */
    public function create(array $overrides = []): array
    {
        $models = [];

        for ($i = 0; $i < $this->count; $i++) {
            $attributes = array_merge($this->states, $overrides);

            // Allow callbacks for dynamic values per instance
            foreach ($attributes as $key => $value) {
                if (is_callable($value)) {
                    $attributes[$key] = $value($i);
                }
            }

            $models[] = clone($this->factory)
                ->state($attributes)
                ->create();
        }

        return $models;
    }
}