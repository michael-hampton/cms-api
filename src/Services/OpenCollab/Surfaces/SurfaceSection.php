<?php

namespace App\Services\OpenCollab\Surfaces;

/**
 * Describes one configurable section on an Open Collab surface/page.
 *
 * The page decides which surface it represents, then the resolver returns the
 * ordered sections to render. Individual section views/providers can evolve
 * independently from the page controller.
 */
final class SurfaceSection
{
    public function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $view,
        private readonly array $layout = [],
        private readonly array $settings = [],
        private readonly array $permissions = [],
    ) {}

    public static function fromArray(array $definition): self
    {
        return new self(
            key: (string) ($definition['key'] ?? ''),
            title: (string) ($definition['title'] ?? $definition['key'] ?? ''),
            view: (string) ($definition['view'] ?? ''),
            layout: is_array($definition['layout'] ?? null) ? $definition['layout'] : [],
            settings: is_array($definition['settings'] ?? null) ? $definition['settings'] : [],
            permissions: array_values($definition['permissions'] ?? []),
        );
    }

    public function key(): string
    {
        return $this->key;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function view(): string
    {
        return $this->view;
    }

    public function layout(): array
    {
        return $this->layout;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }
}
