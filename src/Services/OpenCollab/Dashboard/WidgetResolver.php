<?php

namespace App\Services\OpenCollab\Dashboard;

use App\Models\User;
use App\Repositories\OpenCollab\WidgetSettingsRepository;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\SitePermissionResolver;

/**
 * Produces the final, ordered, deduplicated widget list for a user by merging:
 *
 *   1. System defaults         (config/dashboard.php → 'default')
 *   2. Role defaults           (config/dashboard.php → 'roles.{role}')
 *   3. User overrides          (contributor_dashboard_widgets table)
 *   4. Onboarding gate         (config/dashboard.php → 'onboarding_gated_roles')
 *   5. Widget visibility gate  (DashboardWidgetInterface::visibleFor)
 *
 * Merging rules:
 *   - Role config replaces system defaults entirely when present.
 *   - User overrides are applied on top: position and enabled state.
 *   - Disabled widgets are excluded from the result.
 *   - The resolver never modifies config or DB — it is read-only.
 *
 * Onboarding gate — replaces the old three-separate-dashboards routing:
 *   - Previously: new contributors hit /contributor/onboarding (separate view/controller)
 *   - Now: if the user's role is in onboarding_gated_roles AND the 'onboarding'
 *     widget's visibleFor() returns true (= onboarding incomplete), the resolver
 *     returns ONLY the onboarding widget, regardless of any other config.
 *   - Once onboarding is complete, visibleFor() returns false and the full widget
 *     set is returned. The transition is automatic on the next page load or widget
 *     refresh — no separate route or controller needed.
 */
final class WidgetResolver
{
    public function __construct(
        private readonly WidgetRegistry            $registry,
        private readonly WidgetSettingsRepository  $settingsRepository,
        private readonly SitePermissionResolver    $permissionResolver,
    ) {}

    /**
     * Returns the resolved, ordered, visibility-gated widget list for the user.
     *
     * @return DashboardWidgetInterface[]
     */
    public function resolveForUser(User $user): array
    {
        // ── Onboarding gate ────────────────────────────────────────────────────
        // For gated roles, show only the onboarding widget until setup is done.
        if ($this->isOnboardingGated($user)) {
            return $this->onboardingOnlyWidgets($user);
        }

        return $this->resolveFullWidgetSet($user);
    }

    /**
     * Returns the allowed UI components for a named surface.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allowedForSurface(
        int $userId,
        int $siteId,
        string $surface
    ): array {
        if ($surface === 'dashboard.index') {
            return $this->allowedDashboardComponents($userId);
        }

        $components = $this->registry->componentsForSurface($surface);
        if ($components === []) {
            return [];
        }

        $allowed = array_values(array_filter($components, function (array $component) use ($userId, $siteId): bool {
            if (($component['enabled'] ?? true) !== true) {
                return false;
            }

            foreach ($component['capabilities'] ?? [] as $capability) {
                if (!$this->permissionResolver->allows($userId, $siteId, $capability)) {
                    return false;
                }
            }

            return true;
        }));

        usort($allowed, static function (array $a, array $b): int {
            $sort = ((int) $a['sort_order']) <=> ((int) $b['sort_order']);
            return $sort !== 0 ? $sort : strcmp((string) $a['key'], (string) $b['key']);
        });

        return $allowed;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Whether this user's role requires onboarding to complete before
     * accessing the full dashboard.
     */
    private function isOnboardingGated(User $user): bool
    {
        $siteId = (int) SiteContext::getId();
        $permission = config('dashboard.onboarding_permission');

        if (!$siteId || !$permission || !$this->permissionResolver->allows($user->id, $siteId, $permission)) {
            return false;
        }

        if (!$this->registry->has('onboarding')) {
            return false;
        }

        // Gate is only active while onboarding is incomplete.
        // OnboardingWidget::visibleFor() returns false when done.
        return $this->registry->get('onboarding')->visibleFor($user);
    }

    /**
     * Returns [onboarding] as the sole widget when the gate is active.
     *
     * @return DashboardWidgetInterface[]
     */
    private function onboardingOnlyWidgets(User $user): array
    {
        $widget = $this->registry->get('onboarding');

        return $widget->visibleFor($user) ? [$widget] : [];
    }

    /**
     * Full resolution path: system/role config → user overrides → visibility.
     *
     * @return DashboardWidgetInterface[]
     */
    private function resolveFullWidgetSet(User $user): array
    {
        $baseKeys    = $this->baseKeysForUser($user);

        $userConfigs = $this->settingsRepository->getForUser($user->id);

        $overrideMap = [];
        foreach ($userConfigs as $config) {
            $overrideMap[$config['widget_key']] = $config;
        }

        $resolved = [];

        foreach ($baseKeys as $position => $key) {
            if (!$this->registry->has($key)) {
                continue;
            }

            $widget = $this->registry->get($key);

            if (!$widget->visibleFor($user)) {
                continue;
            }

            $override = $overrideMap[$key] ?? [];
            $enabled  = (bool)($override['enabled'] ?? true);

            if (!$enabled) {
                continue;
            }

            $resolved[] = [
                'widget'   => $widget,
                'position' => (int)($override['position'] ?? $position),
            ];
        }

        // Include any user-added widgets not in base config
        foreach ($overrideMap as $key => $override) {
            if (in_array($key, $baseKeys, true)) {
                continue;
            }

            if (!$this->registry->has($key)) {
                continue;
            }

            $widget  = $this->registry->get($key);
            $enabled = (bool)($override['enabled'] ?? true);

            if (!$enabled || !$widget->visibleFor($user)) {
                continue;
            }

            $resolved[] = [
                'widget'   => $widget,
                'position' => (int)($override['position'] ?? 9999),
            ];
        }

        usort($resolved, fn($a, $b) => $a['position'] <=> $b['position']);

        return array_map(fn($item) => $item['widget'], $resolved);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allowedDashboardComponents(int $userId): array
    {
        $widgets = $this->resolveForUser($this->lightweightUser($userId));
        $allowed = [];

        foreach ($widgets as $sortOrder => $widget) {
            $component = $this->registry->component($widget->key());
            $component['sort_order'] = $sortOrder;
            $allowed[] = $component;
        }

        return $allowed;
    }

    private function lightweightUser(int $userId): User
    {
        /** @var User $user */
        $user = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $user->id = $userId;

        return $user;
    }

    /**
     * Returns the ordered widget keys from config for this user's role.
     * Falls back to system defaults if no role config exists.
     *
     * @return string[]
     */
    private function baseKeysForUser(User $user): array
    {
        $siteId = (int) SiteContext::getId();
        $permissions = $siteId > 0 ? $this->permissionResolver->forUser($user->id, $siteId) : [];

        if (in_array('*', $permissions, true)) {
            return array_values(array_unique(array_merge(...array_values(config('dashboard.permission_sets', [])))));
        }

        $sets = [];
        foreach (config('dashboard.permission_sets', []) as $set => $widgets) {
            $requiredPermission = match ($set) {
                'creator' => 'content.create',
                'reviewer' => 'content.review',
                'finance' => 'payout.view',
                default => null,
            };

            if ($requiredPermission !== null && in_array($requiredPermission, $permissions, true)) {
                $sets = array_merge($sets, $widgets);
            }
        }

        return !empty($sets) ? array_values(array_unique($sets)) : config('dashboard.default', []);
    }

    /**
     * Returns all widgets available to this user regardless of enabled state,
     * each decorated with their current override config.
     *
     * Used by the widget management UI to show the full list so users can
     * re-enable disabled widgets or see what's available to add.
     *
     * Shape per item:
     * {
     *   key:      string,
     *   title:    string,
     *   enabled:  bool,    — current user override; defaults true
     *   position: int,     — current user override; defaults to config order
     * }
     *
     * Returns an empty array while the onboarding gate is active — there is
     * nothing to customise until the dashboard itself is accessible.
     *
     * @return array<int, array{key: string, title: string, enabled: bool, position: int}>
     */
    public function availableForUser(User $user): array
    {
        if ($this->isOnboardingGated($user)) {
            return [];
        }

        $baseKeys    = $this->baseKeysForUser($user);
        $userConfigs = $this->settingsRepository->getForUser($user->id);

        $overrideMap = [];
        foreach ($userConfigs as $config) {
            $overrideMap[$config['widget_key']] = $config;
        }

        $available = [];

        foreach ($baseKeys as $position => $key) {
            if (!$this->registry->has($key)) {
                continue;
            }

            $widget = $this->registry->get($key);

            if (!$widget->visibleFor($user)) {
                continue;
            }

            $override = $overrideMap[$key] ?? [];

            $available[] = [
                'key'      => $key,
                'title'    => $widget->title(),
                'enabled'  => (bool)($override['enabled'] ?? true),
                'position' => (int)($override['position'] ?? $position),
            ];
        }

        // Sort by current position so the management UI opens in display order
        usort($available, fn($a, $b) => $a['position'] <=> $b['position']);

        return $available;
    }
}
