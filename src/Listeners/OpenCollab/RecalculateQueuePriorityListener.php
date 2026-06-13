<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\RiskMarkerStatusChangedEvent;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Services\OpenCollab\Moderation\ModerationQueueService;

/**
 * "Resolved markers retain history; resolved risk reduces score."
 * This is the cross-cutting side effect of any risk marker status change.
 */
class RecalculateQueuePriorityListener
{
    public function __construct(
        private readonly ModerationQueueRepository $queueRepository,
        private readonly ModerationQueueService $queueService,
    ) {
    }

    public function handle(RiskMarkerStatusChangedEvent $event): void
    {
        $marker = $event->marker;

        if ($marker->page_id === null) {
            return; // image-only marker — no queue entry to recalculate
        }

        $entry = $this->queueRepository->openEntryForPage($marker->site_id, $marker->page_id);

        if ($entry === null) {
            return;
        }

        $this->queueService->recalculatePriority($entry->id);
    }
}