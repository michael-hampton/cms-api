<?php

declare(strict_types=1);

namespace App\Jobs\Products;

use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Product\ProductBatchRepository;
use App\Repositories\Product\ProductFulfilmentRepository;
use App\Repositories\Product\ProductFulfilmentRunRepository;

/**
 * Phase 2: Group ProductFulfilment records into ProductBatch records
 * by territory, then dispatch one ExportProductBatchJob per batch.
 *
 * Parallel to BuildPrintBatchesJob. That class is closed for modification.
 *
 * Dispatched by AllProductFulfilmentsCreatedListener.
 */
class BuildProductBatchesJob extends BaseJob
{
    public ?string $queue = 'products';
    public int $tries = 3;
    public int $backoff = 30;

    private ProductFulfilmentRunRepository $runRepository;
    private ProductFulfilmentRepository $fulfilmentRepository;
    private ProductBatchRepository $batchRepository;
    private Logger $logger;

    public function __construct(
        private readonly int $runId,
    )
    {
    }

    public function handle(): void
    {
        $run = $this->runRepository->find($this->runId);

        if (!$run) {
            $this->logger->error('BuildProductBatchesJob: run not found', [
                'run_id' => $this->runId,
            ]);
            return;
        }

        if ($run->isComplete() || $run->isCancelled()) {
            $this->logger->info('BuildProductBatchesJob: run in terminal state, skipping', [
                'run_id' => $this->runId,
                'status' => $run->status,
            ]);
            return;
        }

        $run->markBatching();

        // Group fulfilments by territory — one batch per territory.
        // null territory_id = global/default batch.
        $grouped = $this->fulfilmentRepository->findByRunGroupedByTerritory($this->runId);

        $batches = [];
        foreach ($grouped as $territoryId => $fulfilments) {
            $normalisedTerritoryId = ($territoryId === '' || $territoryId === 'null')
                ? null
                : (int)$territoryId;

            $batches[] = $this->batchRepository->findOrCreateForRunAndTerritory(
                $this->runId,
                $normalisedTerritoryId,
            );
        }

        $run->markBatched();

        $this->logger->info('BuildProductBatchesJob: batches built', [
            'run_id' => $this->runId,
            'batch_count' => count($batches),
        ]);

        foreach ($batches as $batch) {
            dispatch(ExportProductBatchJob::for((int)$batch->id));
        }
    }
}