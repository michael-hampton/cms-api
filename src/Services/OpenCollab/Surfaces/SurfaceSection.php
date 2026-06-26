<?php

namespace App\Services\OpenCollab\Surfaces;

/**
 * Describes one configurable section on an Open Collab surface/page.
 *
 * Pages are intentionally dumb orchestrators: they receive a manifest and the
 * JavaScript surface renderer decides how/when each section loads data.
 */
final class SurfaceSection
{
    public function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $component,
        private readonly ?string $endpoint = null,
        private readonly array $layout = [],
        private readonly array $settings = [],
        private readonly array $permissions = [],
    ) {}

    public static function fromArray(array $definition): self
    {
        return new self(
            key: (string) ($definition['key'] ?? ''),
            title: (string) ($definition['title'] ?? $definition['key'] ?? ''),
            component: (string) ($definition['component'] ?? ''),
            endpoint: isset($definition['endpoint']) ? (string) $definition['endpoint'] : null,
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

    public function component(): string
    {
        return $this->component;
    }

    public function endpoint(): ?string
    {
        return $this->endpoint;
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

    public function toManifest(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'component' => $this->component,
            'endpoint' => $this->endpoint,
            'layout' => $this->layout,
            'settings' => $this->settings,
        ];
    }
}
