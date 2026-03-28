<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\AllFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\BuildPrintBatchesJob;

/**
 * Listens for AllFulfilmentsCreated (Phase 1 complete) and dispatches
 * BuildPrintBatchesJob to begin Phase 2.
 *
 * This is the only Phase 1 → Phase 2 transition point.
 * Nothing else should dispatch BuildPrintBatchesJob directly.
 */
class AllFulfilmentsCreatedListener
{
    public function __construct(
        private readonly Logger $logger,
    )
    {
    }

    public function handle(AllFulfilmentsCreated $event): void
    {
        $printRun = $event->printRun;

        dispatch(BuildPrintBatchesJob::for(), $printRun->id);

        $this->logger->info('AllFulfilmentsCreatedListener: BuildPrintBatchesJob dispatched', [
            'print_run_id' => $printRun->id,
            'total_fulfilments' => $event->totalFulfilments,
        ]);
    }
}