<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Subscriptions\Printing\BatchBuilderService;

/**
 * Phase 2: Build PrintBatch records from the fulfillments created in Phase 1.
 *
 * Dispatched by AllFulfilmentsCreatedListener after AllFulfilmentsCreated fires.
 *
 * Responsibilities:
 *   1. Load PrintRun and IssueDelivery.
 *   2. Transition PrintRun: fulfilling → batching.
 *   3. Delegate to BatchBuilderService (unchanged — owns territory grouping).
 *   4. Transition PrintRun: batching → batched.
 *   5. Dispatch ProcessPrintBatchJob per batch.
 *
 * BatchBuilderService groups by territory_id. One PrintBatch per territory,
 * one global batch when no territories apply.
 */
class BuildPrintBatchesJob extends BaseJob
{
    public string $queue = 'print';
    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly int $printRunId,
    )
    {
    }

    public function handle(
        PrintRunRepository      $printRunRepository,
        IssueDeliveryRepository $issueDeliveryRepository,
        BatchBuilderService     $batchBuilderService,
        Logger                  $logger,
    ): void
    {
        $printRun = $printRunRepository->find($this->printRunId);

        if (!$printRun) {
            $logger->error('BuildPrintBatchesJob: PrintRun not found', [
                'print_run_id' => $this->printRunId,
            ]);
            return;
        }

        if ($printRun->isCancelled() || $printRun->isComplete()) {
            $logger->info('BuildPrintBatchesJob: PrintRun in terminal state, skipping', [
                'print_run_id' => $this->printRunId,
                'status' => $printRun->status,
            ]);
            return;
        }

        $issueDelivery = $issueDeliveryRepository->find($printRun->issue_delivery_id);

        if (!$issueDelivery) {
            $logger->error('BuildPrintBatchesJob: IssueDelivery not found', [
                'print_run_id' => $this->printRunId,
                'issue_delivery_id' => $printRun->issue_delivery_id,
            ]);
            $printRun->markFailed();
            return;
        }

        $printRun->markBatching();

        $logger->info('BuildPrintBatchesJob: building batches', [
            'print_run_id' => $this->printRunId,
            'issue_delivery_id' => $issueDelivery->id,
        ]);

        $batches = $batchBuilderService->buildBatches($issueDelivery);

        $printRun->markBatched();

        $logger->info('BuildPrintBatchesJob: batches built', [
            'print_run_id' => $this->printRunId,
            'batch_count' => $batches->count(),
        ]);

        // Dispatch Phase 3 — one ProcessPrintBatchJob per batch.
        // Export and label generation are parallel (both dispatched from that job).
        foreach ($batches as $batch) {
            dispatch(ProcessPrintBatchJob::for(), app(PrintBatchRepository::class), app(Logger::class), $batch->id);
        }
    }
}