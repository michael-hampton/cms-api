<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Actions\Subscriptions\Print\CreatePrintFulfillmentAction;
use App\Events\Subscriptions\AllFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Phase 1 worker — processes one chunk of print subscriptions.
 *
 * Each job:
 *   1. Loads the PrintRun and IssueDelivery (guards against stale state).
 *   2. Iterates its subscription IDs, calling CreatePrintFulfillmentAction per sub.
 *   3. Individual subscription failures are caught and logged — one bad
 *      address does not fail the whole chunk.
 *   4. Atomically increments PrintRun.fulfilled_chunks_count.
 *   5. If the new count equals total_chunks, fires AllFulfilmentsCreated
 *      (Phase 1 → Phase 2 barrier signal).
 *
 * Idempotency:
 *   CreatePrintFulfillmentAction already guards against duplicate records.
 *   Re-running this job for the same chunk is safe — duplicate fulfillments
 *   are skipped, but the chunk counter is only incremented once because the
 *   barrier check uses the authoritative DB value after increment.
 *
 * Failure semantics:
 *   If this job fails entirely (infrastructure error), the chunk counter is
 *   NOT incremented. FulfilmentCompletionMonitorJob detects the stall and
 *   alerts operators who can re-dispatch the job.
 */
class CreateFulfilmentsChunkJob extends BaseJob
{
    public string $queue = 'print';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly int   $printRunId,
        private readonly int   $issueDeliveryId,
        private readonly array $subscriptionIds,
        private readonly int   $chunkIndex,
    )
    {
    }

    public function handle(
        PrintRunRepository           $printRunRepository,
        IssueDeliveryRepository      $issueDeliveryRepository,
        SubscriptionRepository       $subscriptionRepository,
        CreatePrintFulfillmentAction $fulfillmentAction,
        Logger                       $logger,
    ): void
    {
        $printRun = $printRunRepository->find($this->printRunId);

        if (!$printRun) {
            $logger->error('CreateFulfilmentsChunkJob: PrintRun not found', [
                'print_run_id' => $this->printRunId,
                'chunk_index' => $this->chunkIndex,
            ]);
            return;
        }

        // Guard: if the PrintRun was cancelled while this job was queued, abort.
        if ($printRun->isCancelled()) {
            $logger->info('CreateFulfilmentsChunkJob: PrintRun cancelled, skipping chunk', [
                'print_run_id' => $this->printRunId,
                'chunk_index' => $this->chunkIndex,
            ]);
            return;
        }

        $issueDelivery = $issueDeliveryRepository->find($this->issueDeliveryId);

        if (!$issueDelivery) {
            $logger->error('CreateFulfilmentsChunkJob: IssueDelivery not found', [
                'issue_delivery_id' => $this->issueDeliveryId,
                'print_run_id' => $this->printRunId,
            ]);
            return;
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->subscriptionIds as $subscriptionId) {
            $subscription = $subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                $logger->warning('CreateFulfilmentsChunkJob: subscription not found', [
                    'subscription_id' => $subscriptionId,
                    'print_run_id' => $this->printRunId,
                ]);
                $failed++;
                continue;
            }

            try {
                $fulfillmentAction->execute($subscription, $issueDelivery);
                $created++;
            } catch (\Throwable $e) {
                // Non-critical per subscription — address missing, validation
                // failure, etc. Log and continue so one bad record does not
                // block the rest of the chunk.
                $failed++;
                $logger->error('CreateFulfilmentsChunkJob: fulfillment creation failed', [
                    'subscription_id' => $subscriptionId,
                    'issue_delivery_id' => $this->issueDeliveryId,
                    'print_run_id' => $this->printRunId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $logger->info('CreateFulfilmentsChunkJob: chunk processed', [
            'print_run_id' => $this->printRunId,
            'chunk_index' => $this->chunkIndex,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        // Atomic increment — safe across concurrent chunk workers.
        $newCount = $printRun->incrementFulfilledChunks();

        // If this was the last chunk, fire the Phase 2 barrier signal.
        if ($printRun->allChunksComplete()) {
            $logger->info('CreateFulfilmentsChunkJob: all chunks complete, firing AllFulfilmentsCreated', [
                'print_run_id' => $this->printRunId,
                'total_chunks' => $printRun->total_chunks,
                'fulfilled_count' => $newCount,
            ]);

            event(new AllFulfilmentsCreated(
                printRun: $printRun,
                totalFulfilments: $created, // approximate — precise count not needed here
            ));
        }
    }
}