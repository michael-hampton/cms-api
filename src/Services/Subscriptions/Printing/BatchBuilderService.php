<?php

namespace App\Services\Subscriptions\Printing;

use App\Enums\Subscriptions\PrintExportFormat;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\PrintBatch;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;

/**
 * Groups already-persisted fulfilment records by territory and ensures one
 * PrintBatch exists per territory.
 *
 * Grouping is done at the database level (not in-memory) to support large
 * subscriber volumes (per non-functional requirement).
 *
 * Single reason to change: the rules for how fulfilments map to batches.
 * Territory resolution is owned by FulfilmentDecisionService.
 * Export is owned by PrintBatchExportService.
 */
class BatchBuilderService
{
    public function __construct(
        private readonly PrintBatchRepository       $batchRepository,
        private readonly PrintFulfillmentRepository $fulfilmentRepository,
    )
    {
    }

    /**
     * Build (or locate) one batch per territory for the given issue delivery.
     *
     * Returns the collection of batches that were created or found. The caller
     * (typically ExportPrintBatchJob) dispatches an export job per batch.
     *
     * @return Collection<PrintBatch>
     */
    public function buildBatches(
        IssueDelivery     $issueDelivery,
        PrintExportFormat $format = PrintExportFormat::CSV,
    ): Collection
    {
        // DB-level groupBy avoids loading all fulfilments into memory.
        $grouped = $this->fulfilmentRepository->findByIssueDeliveryGroupedByTerritory($issueDelivery->id);

        $batches = [];

        foreach ($grouped as $territoryId => $fulfilments) {
            // groupBy returns empty string for null values in some ORM implementations;
            // normalise back to null so the repository receives the correct type.
            $normalisedTerritoryId = ($territoryId === '' || $territoryId === 'null')
                ? null
                : (int)$territoryId;

            $batch = $this->batchRepository->findOrCreateForIssueDeliveryAndTerritory(
                $issueDelivery->id,
                $normalisedTerritoryId,
                $format,
            );

            $batches[] = $batch;
        }

        return new Collection($batches);
    }
}