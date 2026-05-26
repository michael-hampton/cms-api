<?php
namespace App\Services\OpenCollab\Dashboard\Widgets;

use App\Models\User;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;

final class ReviewQueueWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {}

    public function key(): string   { return 'review_queue'; }
    public function title(): string { return 'Review Queue'; }

    public function visibleFor(User $user): bool
    {
        return true;
    }

    public function data(User $user): array
    {
        return [
            'pending_count' => $this->pageRepository->countPendingReview(),
            'items' => $this->pageRepository->getPendingReview()
                ->map(function ($item) {
                    return [
                        'id'         => $item->id,
                        'title'      => $item->title,
                        'status'     => $item->status,

                        'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
                    ];
                })
                ->values()
                ->toArray(),
        ];
    }
}
