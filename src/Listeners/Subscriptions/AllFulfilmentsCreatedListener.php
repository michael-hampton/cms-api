<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Events\Subscriptions\AllFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\BuildPrintBatchesJob;
use App\Jobs\Subscriptions\GeneratePrintOrderJob;
use App\Services\Workflow\WorkflowRunRecorderFactory;

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
        private readonly WorkflowRunRecorderFactory $recorderFactory,
        private readonly Logger $logger,
    )
    {
    }

    public function handle(AllFulfilmentsCreated $event): void
    {
        $printRun = $event->printRun;

        dispatch(GeneratePrintOrderJob::for((int)$printRun->issue_delivery_id));

        dispatch(BuildPrintBatchesJob::for((int)$printRun->id));

        $this->recorderFactory
            ->forPrintRun($event->printRun, 'phase_1', WorkflowRunStatus::BATCHING)
            ->record(WorkflowStageResult::succeeded([
                'total_chunks' => $event->printRun->total_chunks,
                'total_fulfilments' => $event->totalFulfilments,
            ]));

        $this->logger->info('AllFulfilmentsCreatedListener: BuildPrintBatchesJob dispatched', [
            'print_run_id' => $printRun->id,
            'total_fulfilments' => $event->totalFulfilments,
        ]);
    }
}