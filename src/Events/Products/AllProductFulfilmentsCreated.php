<?php

declare(strict_types=1);

namespace App\Events\Products;

use App\Models\ProductFulfilmentRun;

/**
 * Fired when all CreateProductFulfilmentsChunkJob workers have completed
 * and the ProductFulfilmentRun's fulfilled_chunks_count equals total_chunks.
 *
 * Phase 1 → Phase 2 barrier signal for the product fulfilment pipeline.
 *
 * Listeners:
 *   - AllProductFulfilmentsCreatedListener → dispatches BuildProductBatchesJob
 */
final class AllProductFulfilmentsCreated
{
    public function __construct(
        public readonly ProductFulfilmentRun $fulfilmentRun,
        public readonly int                  $totalFulfilments,
    )
    {
    }
}