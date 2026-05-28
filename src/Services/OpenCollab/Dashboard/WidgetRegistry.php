<?php

namespace App\Services\OpenCollab\Dashboard;

use App\Models\User;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;

/**
 * Holds all registered dashboard widgets.
 *
 * Registration happens in DashboardServiceProvider (and module providers).
 * Controllers never contain a widget list — they ask the registry.
 *
 * Usage:
 *   $registry->register(new EarningsWidget(...));
 *   $registry->all();
 *   $registry->forUser($user);
 *   $registry->get('earnings');
 */
final class WidgetRegistry
{
    /** @var array<string, DashboardWidgetInterface> Keyed by widget->key() */
    private array $widgets = [];
    /** @var array<string, array<string, mixed>> */
    private array $components = [];

    public function register(DashboardWidgetInterface $widget, array $permissions = []): void
    {
        $this->widgets[$widget->key()] = $widget;
        $this->components[$widget->key()] = [
            'key' => $widget->key(),
            'type' => 'dashboard_widget',
            'surface' => 'dashboard.index',
            'label' => $widget->title(),
            'capabilities' => array_values($permissions),
            'component' => $widget->key(),
            'sort_order' => 0,
            'enabled' => true,
        ];
    }

    /**
     * Register a non-dashboard UI component.
     *
     * @param array{
     *   key: string,
     *   type: string,
     *   surface: string,
     *   label: string,
     *   capabilities?: array<int, string>,
     *   component: string,
     *   sort_order?: int,
     *   enabled?: bool
     * } $definition
     */
    public function registerComponent(array $definition): void
    {
        $key = (string) ($definition['key'] ?? '');
        if ($key === '') {
            throw new \InvalidArgumentException('UI component key is required.');
        }

        $this->components[$key] = [
            'key' => $key,
            'type' => (string) ($definition['type'] ?? 'page_panel'),
            'surface' => (string) ($definition['surface'] ?? ''),
            'label' => (string) ($definition['label'] ?? $key),
            'capabilities' => array_values($definition['capabilities'] ?? (isset($definition['capability']) ? [(string) $definition['capability']] : [])),
            'component' => (string) ($definition['component'] ?? $key),
            'sort_order' => (int) ($definition['sort_order'] ?? 0),
            'enabled' => (bool) ($definition['enabled'] ?? true),
        ];
    }

    /**
     * All registered widgets, unfiltered.
     *
     * @return DashboardWidgetInterface[]
     */
    public function all(): array
    {
        return array_values($this->widgets);
    }

    /**
     * All widgets visible to the given user.
     *
     * @return DashboardWidgetInterface[]
     */
    public function forUser(User $user): array
    {
        return array_values(
            array_filter($this->widgets, fn($w) => $w->visibleFor($user))
        );
    }

    /**
     * Retrieve a single widget by key.
     *
     * @throws \InvalidArgumentException when the key is not registered
     */
    public function get(string $key): DashboardWidgetInterface
    {
        if (!isset($this->widgets[$key])) {
            throw new \InvalidArgumentException("Widget [{$key}] is not registered.");
        }

        return $this->widgets[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->widgets[$key]);
    }

    public function permissionsFor(string $key): array
    {
        return $this->components[$key]['capabilities'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function component(string $key): array
    {
        if (!isset($this->components[$key])) {
            throw new \InvalidArgumentException("Widget [{$key}] is not registered.");
        }

        return $this->components[$key];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function componentsForSurface(string $surface): array
    {
        $components = array_values(array_filter(
            $this->components,
            static fn(array $component): bool => ($component['surface'] ?? null) === $surface
        ));

        usort($components, static function (array $a, array $b): int {
            $sort = ((int) $a['sort_order']) <=> ((int) $b['sort_order']);
            return $sort !== 0 ? $sort : strcmp((string) $a['key'], (string) $b['key']);
        });

        return $components;
    }
}
