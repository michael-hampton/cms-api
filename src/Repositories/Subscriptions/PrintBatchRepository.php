<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\PrintBatchStatus;
use App\Enums\Subscriptions\PrintExportFormat;
use App\Models\Model;
use App\Models\PrintBatch;

class PrintBatchRepository
{
    public function createForIssueDelivery(int $issueDeliveryId, PrintExportFormat $format = PrintExportFormat::CSV): Model
    {
        return PrintBatch::create([
            'issue_delivery_id' => $issueDeliveryId,
            'status' => PrintBatchStatus::QUEUED->value,
            'format' => $format->value,
        ]);
    }

    public function findOrFail(int $id): Model
    {
        $batch = $this->find($id);

        if (!$batch) {
            throw new \RuntimeException("PrintBatch #{$id} not found");
        }

        return $batch;
    }

    public function find(int $id): ?Model
    {
        return PrintBatch::find($id);
    }
}