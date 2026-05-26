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
    private array $permissions = [];

    public function register(DashboardWidgetInterface $widget, array $permissions = []): void
    {
        $this->widgets[$widget->key()] = $widget;
        $this->permissions[$widget->key()] = $permissions;
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
        return $this->permissions[$key] ?? [];
    }
}
