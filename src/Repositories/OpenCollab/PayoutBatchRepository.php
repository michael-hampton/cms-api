<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\PayoutBatchStatus;
use App\Enums\OpenCollab\PayoutStatus;
use App\Models\Model;
use App\Models\Payout;
use App\Models\PayoutBatch;
use App\Repositories\Repository;

class PayoutBatchRepository extends Repository
{
    public function createDraft(
        int $siteId,
        ?int $accrualWindowId = null,
        ?int $createdBy = null,
    ): Model {
        return $this->create([
            'site_id' => $siteId,
            'accrual_window_id' => $accrualWindowId,
            'status' => PayoutBatchStatus::Draft->value,
            'created_by' => $createdBy,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function markProcessing(int $batchId): PayoutBatch
    {
        $this->update($batchId, [
            'status' => PayoutBatchStatus::Processing->value,
            'started_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        return $this->findOrFail($batchId);
    }

    public function markCompleted(int $batchId): PayoutBatch
    {
        $this->update($batchId, [
            'status' => PayoutBatchStatus::Completed->value,
            'completed_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        return $this->findOrFail($batchId);
    }

    public function markFailed(int $batchId): PayoutBatch
    {
        $this->update($batchId, [
            'status' => PayoutBatchStatus::Failed->value,
            'failed_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        return $this->findOrFail($batchId);
    }

    public function markPartiallyFailed(int $batchId): PayoutBatch
    {
        $this->update($batchId, [
            'status' => PayoutBatchStatus::PartiallyFailed->value,
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        return $this->findOrFail($batchId);
    }

    public function markCancelled(int $batchId): PayoutBatch
    {
        $this->update($batchId, [
            'status' => PayoutBatchStatus::Cancelled->value,
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        return $this->findOrFail($batchId);
    }

    public function refreshStatusFromPayouts(int $batchId): PayoutBatch
    {
        $this->findOrFail($batchId);

        $statuses = Payout::where('batch_id', $batchId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        if ($statuses->count() === 0) {
            return $this->findOrFail($batchId);
        }

        $counts = [];
        foreach ($statuses as $row) {
            $counts[$row->status] = (int)$row->total;
        }

        $hasFailed = ($counts[PayoutStatus::Failed->value] ?? 0) > 0;
        $hasPaid = ($counts[PayoutStatus::Paid->value] ?? 0) > 0;
        $hasPendingWork = ($counts[PayoutStatus::Pending->value] ?? 0) > 0
            || ($counts[PayoutStatus::Approved->value] ?? 0) > 0;

        if ($hasFailed && ($hasPaid || $hasPendingWork)) {
            return $this->markPartiallyFailed($batchId);
        }

        if ($hasFailed) {
            return $this->markFailed($batchId);
        }

        if (!$hasPendingWork) {
            return $this->markCompleted($batchId);
        }

        return $this->markProcessing($batchId);
    }

    public function findOrFail(int $batchId): PayoutBatch
    {
        $batch = $this->find($batchId);

        if (!$batch) {
            throw new \InvalidArgumentException("Payout batch [{$batchId}] not found.");
        }

        return $batch;
    }

    protected function getModelClass(): string
    {
        return PayoutBatch::class;
    }
}
