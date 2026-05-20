<?php

namespace App\Services\OpenCollab\Dashboard;

use App\Repositories\OpenCollab\WidgetSettingsRepository;

/**
 * Manages user widget override persistence.
 *
 * Responsibilities:
 *   - Validate that widget keys are registered before persisting
 *   - Enforce that position values are non-negative integers
 *   - Delegate all writes to WidgetSettingsRepository
 *
 * This service does NOT resolve the final widget list — that is
 * WidgetResolver's job. It only manages the user's saved preferences.
 *
 * Rules (TICKET 3.3):
 *   - Users can disable any widget
 *   - Users can reorder widgets by setting position
 *   - Users can store widget-specific settings (arbitrary JSON)
 *   - Overrides take priority over role + system config (applied in WidgetResolver)
 *   - Disabled widgets are excluded entirely from the resolved list
 */
class WidgetSettingsService
{
    public function __construct(
        private readonly WidgetSettingsRepository $settingsRepository,
        private readonly WidgetRegistry           $widgetRegistry,
    ) {}

    /**
     * Save a single widget override for a user.
     *
     * @throws \InvalidArgumentException if the widget key is not registered
     */
    public function saveWidgetConfig(
        int    $userId,
        string $widgetKey,
        bool   $enabled,
        int    $position,
        array  $settings = [],
    ): void {
        $this->assertWidgetExists($widgetKey);

        if ($position < 0) {
            throw new \InvalidArgumentException("Position must be a non-negative integer, got [{$position}].");
        }

        $this->settingsRepository->saveWidgetConfig($userId, $widgetKey, $enabled, $position, $settings);
    }

    /**
     * Bulk-update widget positions for a user (drag-and-drop reorder).
     *
     * Validates every key before writing anything — all-or-nothing.
     *
     * @param array<int, array{widget_key: string, position: int}> $positions
     *
     * @throws \InvalidArgumentException if any key is not registered or any
     *                                   position is invalid
     */
    public function updatePositions(int $userId, array $positions): void
    {
        foreach ($positions as $item) {
            $this->assertWidgetExists($item['widget_key'] ?? '');

            if (!isset($item['position']) || (int)$item['position'] < 0) {
                throw new \InvalidArgumentException(
                    "Invalid position for widget [{$item['widget_key']}]."
                );
            }
        }

        $this->settingsRepository->updatePositions($userId, collect($positions));
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function assertWidgetExists(string $key): void
    {
        if ($key === '' || !$this->widgetRegistry->has($key)) {
            throw new \InvalidArgumentException("Widget [{$key}] is not registered.");
        }
    }
}