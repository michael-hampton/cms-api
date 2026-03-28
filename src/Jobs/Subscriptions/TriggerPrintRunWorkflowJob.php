<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Enums\Subscriptions\PrintRunStatus;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;

/**
 * Phase 1 entry point for the print pipeline.
 *
 * Responsibilities (only these):
 *   1. Load the IssueDelivery — bail if missing.
 *   2. Cancel any existing pending PrintRuns for this delivery (idempotency).
 *   3. Create a new PrintRun in Pending status.
 *   4. Dispatch CreatePrintFulfillmentsJob to handle subscription querying,
 *      chunking, and chunk job dispatch.
 *
 * This job deliberately knows nothing about subscriptions, chunk sizes,
 * or monitor delays. That is CreatePrintFulfillmentsJob's responsibility.
 * Keeping this job thin means it is trivially testable and retryable.
 */
class TriggerPrintRunWorkflowJob extends BaseJob
{
    public string $queue = 'print';
    public int $tries = 3;

    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly PrintRunRepository      $printRunRepository,
        private readonly Logger                  $logger,
    )
    {
    }

    public function handle(
        int $issueDeliveryId,
    ): void
    {
        $issueDelivery = $this->issueDeliveryRepository->find($issueDeliveryId);

        if (!$issueDelivery) {
            $this->logger->error('TriggerPrintRunWorkflowJob: IssueDelivery not found', [
                'issue_delivery_id' => $issueDeliveryId,
            ]);
            return;
        }

        // Cancel any pending PrintRuns for this delivery before creating a new
        // one — prevents orphaned runs if this job is retried or re-triggered.
        $this->printRunRepository->cancelAllPendingForIssueDelivery($issueDelivery->id);

        $printRun = $this->printRunRepository->create([
            'issue_delivery_id' => $issueDelivery->id,
            'status' => PrintRunStatus::PENDING->value,
            'is_regional' => false,
            'driver_sync_enabled' => false,
            'total_chunks' => 0,
            'fulfilled_chunks_count' => 0,
        ]);

        $this->logger->info('TriggerPrintRunWorkflowJob: PrintRun created', [
            'print_run_id' => $printRun->id,
            'issue_delivery_id' => $issueDelivery->id,
        ]);

        dispatch(CreatePrintFulfillmentsJob::for(), $printRun->id, $issueDelivery->id);
    }
}