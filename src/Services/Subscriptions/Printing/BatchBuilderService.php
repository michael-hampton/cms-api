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
 * Territory override path:
 *   When the IssueDelivery carries a territory_id override, territory grouping
 *   is skipped entirely. A single batch is produced for the whole delivery scope.
 *   The batch's territory_id operationally defines the territory; fulfilment
 *   territory_id values are left unchanged.
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
     * Build (or locate) batches for the given issue delivery.
     *
     * When the issue delivery has a territory_id override, a single batch is
     * returned for the entire delivery — territory grouping is bypassed.
     *
     * Otherwise, one batch is produced per unique territory found across
     * fulfilment records. The caller (typically ExportPrintBatchJob) dispatches
     * an export job per batch.
     *
     * @return Collection<PrintBatch>
     */
    public function buildBatches(
        IssueDelivery     $issueDelivery,
        PrintExportFormat $format = PrintExportFormat::CSV,
    ): Collection
    {
        if ($this->hasOverrideTerritory($issueDelivery)) {
            return $this->buildSingleOverrideBatch($issueDelivery, $format);
        }

        return $this->buildBatchesGroupedByTerritory($issueDelivery, $format);
    }

    // -------------------------------------------------------------------------
    // Private — override path
    // -------------------------------------------------------------------------

    /**
     * Returns true when the issue delivery has a territory_id that should
     * override per-fulfilment territory grouping.
     */
    private function hasOverrideTerritory(IssueDelivery $issueDelivery): bool
    {
        return $issueDelivery->territory_id && $issueDelivery->territory_id !== null;
    }

    /**
     * Produces one batch for the full delivery scope, stamped with the override
     * territory. Fulfilment territory_id values are intentionally not modified.
     */
    private function buildSingleOverrideBatch(
        IssueDelivery     $issueDelivery,
        PrintExportFormat $format,
    ): Collection
    {
        $batch = $this->batchRepository->findOrCreateForIssueDeliveryAndTerritory(
            $issueDelivery->id,
            (int)$issueDelivery->territory_id,
            $format,
        );

        return new Collection([$batch]);
    }

    // -------------------------------------------------------------------------
    // Private — normal grouped path
    // -------------------------------------------------------------------------

    /**
     * Groups fulfilments by territory_id at the DB level and produces one batch
     * per territory. A null territory_id produces a global (unscoped) batch.
     */
    private function buildBatchesGroupedByTerritory(
        IssueDelivery     $issueDelivery,
        PrintExportFormat $format,
    ): Collection
    {
        // DB-level groupBy avoids loading all fulfilments into memory.
        $grouped = $this->fulfilmentRepository->findByIssueDeliveryGroupedByTerritory($issueDelivery->id);

        $batches = [];

        foreach ($grouped as $territoryId => $fulfilments) {
            // groupBy returns empty string for null values in some ORM
            // implementations; normalise back to null so the repository
            // receives the correct type.
            $normalisedTerritoryId = ($territoryId === '' || $territoryId === 'null')
                ? null
                : (int)$territoryId;

            $batches[] = $this->batchRepository->findOrCreateForIssueDeliveryAndTerritory(
                $issueDelivery->id,
                $normalisedTerritoryId,
                $format,
            );
        }

        return new Collection($batches);
    }
}