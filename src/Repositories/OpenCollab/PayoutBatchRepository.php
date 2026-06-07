<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\PayoutBatchStatus;
use App\Models\Model;
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