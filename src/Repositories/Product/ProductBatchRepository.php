<?php

declare(strict_types=1);

namespace App\Repositories\Product;

use App\Enums\Products\ProductBatchStatus;
use App\Enums\Subscriptions\PrintExportFormat;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\ProductBatch;
use App\Repositories\Repository;

/**
 * Persistence for ProductBatch records.
 *
 * Parallel to PrintBatchRepository. No business logic lives here.
 */
class ProductBatchRepository extends Repository
{
    /**
     * Find or create a batch for a fulfilment run + territory.
     *
     * Idempotent — returns an existing QUEUED or EXPORTING batch
     * rather than creating a duplicate.
     */
    public function findOrCreateForRunAndTerritory(
        int               $fulfilmentRunId,
        ?int              $territoryId,
        PrintExportFormat $format = PrintExportFormat::CSV,
    ): Model
    {
        $existing = ProductBatch::where('fulfilment_run_id', $fulfilmentRunId)
            ->where('territory_id', $territoryId)
            ->whereIn('status', [
                ProductBatchStatus::QUEUED->value,
                ProductBatchStatus::EXPORTING->value,
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        return ProductBatch::create([
            'fulfilment_run_id' => $fulfilmentRunId,
            'territory_id' => $territoryId,
            'status' => ProductBatchStatus::QUEUED->value,
            'format' => $format->value,
            'export_attempt_count' => 0,
        ]);
    }

    /**
     * All batches for a fulfilment run.
     *
     * @return Collection<ProductBatch>
     */
    public function findByRun(int $fulfilmentRunId): Collection
    {
        return ProductBatch::where('fulfilment_run_id', $fulfilmentRunId)->get();
    }

    protected function getModelClass(): string
    {
        return ProductBatch::class;
    }
}