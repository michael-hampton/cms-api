<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Enums\Subscriptions\LabelRunStatus;
use App\Framework\Support\Collection;
use App\Models\LabelRun;
use App\Models\Model;

class LabelRunRepository
{
    /**
     * Create a LabelRun in Pending status for one SubscriptionIssueFulfilment record.
     */
    public function createForSubscriptionIssueFulfilment(
        int               $subscriptionIssueFulfilmentId,
        int               $subscriptionId,
        LabelExportFormat $format,
        ?int              $printBatchId = null,
    ): Model
    {
        return LabelRun::create([
            'subscription_issue_fulfilment_id' => $subscriptionIssueFulfilmentId,
            'print_batch_id' => $printBatchId,
            'subscription_id' => $subscriptionId,
            'status' => LabelRunStatus::Pending->value,
            'format' => $format->value,
            'attempt_count' => 0,
        ]);
    }

    public function find(int $id): ?Model
    {
        return LabelRun::find($id);
    }

    /**
     * All LabelRuns for a given PrintBatch.
     *
     * @return Collection<LabelRun>
     */
    public function findByBatch(int $printBatchId): Collection
    {
        return LabelRun::where('print_batch_id', $printBatchId)->get();
    }

    /**
     * Pending LabelRuns for a given PrintBatch — used by GenerateLabelRunsJob
     * to dispatch only the ones not yet processed (idempotency guard).
     *
     * @return Collection<LabelRun>
     */
    public function findPendingByBatch(int $printBatchId): Collection
    {
        return LabelRun::where('print_batch_id', $printBatchId)
            ->where('status', LabelRunStatus::Pending->value)
            ->get();
    }

    /**
     * Retryable failed LabelRuns for a given PrintBatch.
     *
     * @return Collection<LabelRun>
     */
    public function findRetryableByBatch(int $printBatchId, int $maxAttempts = 3): Collection
    {
        return LabelRun::where('print_batch_id', $printBatchId)
            ->where('status', LabelRunStatus::Failed->value)
            ->where('attempt_count', '<', $maxAttempts)
            ->get();
    }

    /**
     * True when a LabelRun already exists for this SubscriptionIssueFulfilment + PrintBatch
     * combination. Used as an idempotency guard in GenerateLabelRunsJob.
     */
    public function existsForSubscriptionIssueFulfilmentAndBatch(int $subscriptionIssueFulfilmentId, int $printBatchId): bool
    {
        return LabelRun::where('subscription_issue_fulfilment_id', $subscriptionIssueFulfilmentId)
            ->where('print_batch_id', $printBatchId)
            ->exists();
    }

    /**
     * Count of LabelRuns by status for a given PrintBatch.
     * Used for batch-level observability.
     *
     * @return array{pending: int, generating: int, complete: int, failed: int}
     */
    public function countByStatusForBatch(int $printBatchId): array
    {
        $counts = LabelRun::where('print_batch_id', $printBatchId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending' => (int)($counts[LabelRunStatus::Pending->value] ?? 0),
            'generating' => (int)($counts[LabelRunStatus::Generating->value] ?? 0),
            'complete' => (int)($counts[LabelRunStatus::Complete->value] ?? 0),
            'failed' => (int)($counts[LabelRunStatus::Failed->value] ?? 0),
        ];
    }
}