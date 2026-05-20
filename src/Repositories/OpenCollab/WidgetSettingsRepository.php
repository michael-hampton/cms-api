<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;

/**
 * Persistence for per-user widget configuration overrides.
 *
 * Table: contributor_dashboard_widgets
 *   id, user_id, widget_key, enabled, position, settings (json), timestamps
 *
 * Storage only — no widget resolution or business rules.
 */
class WidgetSettingsRepository
{
    private const TABLE = 'oc_contributor_dashboard_widgets';

    public function __construct(
        private readonly Database $db,
    )
    {
    }

    /**
     * Returns all widget config rows for a user.
     *
     * @return Collection<int, array{
     *     widget_key: string,
     *     enabled: bool,
     *     position: int,
     *     settings: array
     * }>
     */
    public function getForUser(int $userId): Collection
    {
        return $this->db
            ->table(self::TABLE)
            ->select([
                'widget_key',
                'enabled',
                'position',
                'settings',
            ])
            ->where('user_id', $userId)
            ->orderBy('position')
            ->get()
            ->map(
                fn(object|array $row): array => [
                    'widget_key' => data_get($row, 'widget_key'),
                    'enabled' => (bool)data_get($row, 'enabled'),
                    'position' => (int)data_get($row, 'position'),
                    // Fix: Fallback to '[]' string if data_get returns null
                    'settings' => json_decode(
                            data_get($row, 'settings') ?? '[]',
                            true
                        ) ?? [],
                ]
            )
            ->values();
    }

    /**
     * Upserts a widget config row.
     */
    public function saveWidgetConfig(
        int    $userId,
        string $widgetKey,
        bool   $enabled,
        int    $position,
        array  $settings = []
    ): void
    {
        $existing = $this->db
            ->table(self::TABLE)
            ->where('user_id', $userId)
            ->where('widget_key', $widgetKey)
            ->exists();

        $payload = [
            'enabled' => $enabled,
            'position' => $position,
            'settings' => json_encode($settings),
            'updated_at' => now(),
        ];

        if ($existing) {
            $this->db
                ->table(self::TABLE)
                ->where('user_id', $userId)
                ->where('widget_key', $widgetKey)
                ->update($payload);

            return;
        }

        $this->db
            ->table(self::TABLE)
            ->insert([
                'user_id' => $userId,
                'widget_key' => $widgetKey,
                'created_at' => now(),
                ...$payload,
            ]);
    }

    /**
     * Bulk update widget ordering.
     *
     * Example:
     * [
     *     ['widget_key' => 'earnings', 'position' => 0],
     *     ['widget_key' => 'tasks', 'position' => 1],
     * ]
     *
     * @param Collection<int, array{
     *     widget_key: string,
     *     position: int
     * }> $positions
     */
    public function updatePositions(
        int        $userId,
        Collection $positions
    ): void
    {
        $this->db->transaction(
            function () use ($userId, $positions): void {
                $positions->each(
                    function (array $item) use ($userId): void {
                        $this->db
                            ->table(self::TABLE)
                            ->where('user_id', $userId)
                            ->where(
                                'widget_key',
                                $item['widget_key']
                            )
                            ->update([
                                'position' => (int)$item['position'],
                                'updated_at' => now(),
                            ]);
                    }
                );
            }
        );
    }
}