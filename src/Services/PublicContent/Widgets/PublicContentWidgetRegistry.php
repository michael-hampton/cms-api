<?php

namespace App\Services\PublicContent\Widgets;

use InvalidArgumentException;

final class PublicContentWidgetRegistry
{
    /** @var array<string, PublicContentWidgetDefinition> */
    private array $definitions = [];

    /** @param iterable<PublicContentWidgetDefinition> $definitions */
    public function __construct(iterable $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public function register(PublicContentWidgetDefinition $definition): void
    {
        $key = $definition->key();

        if ($key === '') {
            throw new InvalidArgumentException('Widget key cannot be empty.');
        }

        $this->definitions[$key] = $definition;
    }

    public function get(string $key): PublicContentWidgetDefinition
    {
        if (!isset($this->definitions[$key])) {
            throw new InvalidArgumentException("Public content widget [{$key}] is not registered.");
        }

        return $this->definitions[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /** @return list<PublicContentWidgetDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
