<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\PrintBatchStatus;
use App\Enums\Subscriptions\PrintExportFormat;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\PrintBatch;
use App\Models\PrintRun;

class PrintBatchRepository
{
    /**
     * Create a batch for an issue delivery with no territory (global/default edition).
     * Used by legacy code paths that do not yet carry territory context.
     */
    public function createForIssueDelivery(
        int               $issueDeliveryId,
        PrintExportFormat $format = PrintExportFormat::CSV,
    ): Model
    {
        return PrintBatch::create([
            'issue_delivery_id' => $issueDeliveryId,
            'status' => PrintBatchStatus::QUEUED->value,
            'format' => $format->value,
            'territory_id' => null,
        ]);
    }

    /**
     * Find or create a batch for a specific issue delivery + territory combination.
     *
     * Idempotent — returns an existing QUEUED or EXPORTING batch rather than
     * creating a duplicate. Used by BatchBuilderService after fulfilments are
     * already persisted and grouped by territory.
     *
     * A null territory_id produces the global/default-edition batch.
     */
    public function findOrCreateForIssueDeliveryAndTerritory(
        int               $issueDeliveryId,
        ?int              $territoryId,
        PrintExportFormat $format = PrintExportFormat::CSV,
    ): Model
    {

        $existing = PrintBatch::where('issue_delivery_id', $issueDeliveryId)
            ->when(!empty($territoryId), function ($query) use ($territoryId) {
                return $query->where('territory_id', $territoryId);
            })
            ->whereIn('status', [
                PrintBatchStatus::QUEUED->value,
                PrintBatchStatus::BATCH_EXPORTING->value,
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        return PrintBatch::create([
            'issue_delivery_id' => $issueDeliveryId,
            'status' => PrintBatchStatus::QUEUED->value,
            'format' => $format->value,
            'territory_id' => $territoryId,
        ]);
    }

    /**
     * All batches for an issue delivery across all territories.
     *
     * @return Collection<PrintBatch>
     */
    public function findByIssueDelivery(int $issueDeliveryId): Collection
    {
        return PrintBatch::where('issue_delivery_id', $issueDeliveryId)->get();
    }

    public function findOrFail(int $id): Model
    {
        $batch = PrintBatch::find($id);

        if (!$batch) {
            throw new \RuntimeException("PrintBatch #{$id} not found");
        }

        return $batch;
    }

    public function find(int $id): ?Model
    {
        return PrintBatch::find($id);
    }

    public function attachToPrintRun(PrintRun $printRun, ?int $territoryId = null): Collection
    {
        $query = PrintBatch::query()
            ->where('issue_delivery_id', $printRun->issue_delivery_id)

            // Only batches not already attached
            ->whereNull('print_run_id')

            // Only pending batches
            ->where('status', PrintBatchStatus::PENDING->value);

        if ($territoryId !== null) {
            $query->where('territory_id', $territoryId);
        }

        $batches = $query->get();

        if ($batches->isEmpty()) {
            return collect();
        }

        // 🔥 Bulk update (don’t loop like a junior)
        PrintBatch::whereIn('id', $batches->pluck('id'))
            ->update([
                'print_run_id' => $printRun->id,
            ]);

        // Keep in-memory models correct
        return $batches->each(function ($batch) use ($printRun) {
            $batch->print_run_id = $printRun->id;
        });
    }
}