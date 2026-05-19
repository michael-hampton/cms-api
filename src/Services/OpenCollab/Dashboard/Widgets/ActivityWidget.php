<?php

namespace App\Services\OpenCollab\Dashboard\Widgets;

use App\Models\User;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;

/**
 * Activity widget — last N contributor activity events, sorted descending.
 */
final class ActivityWidget implements DashboardWidgetInterface
{
    private const LIMIT = 10;

    public function __construct(
        private readonly ActivityRepository $activityRepository,
    ) {}

    public function key(): string
    {
        return 'activity';
    }

    public function title(): string
    {
        return 'Recent Activity';
    }

    public function visibleFor(User $user): bool
    {
        return true;
    }

    public function data(User $user): array
    {
        return [
            'items' => $this->activityRepository
                ->forContributor($user->id, self::LIMIT)
                ->map(function ($item) {
                    return array_merge(
                        $item->toArray(),
                        [
                            'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                            'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
                        ]
                    );
                })
                ->values()
                ->toArray(),
        ];
    }
}