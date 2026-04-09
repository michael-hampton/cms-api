<?php

declare(strict_types=1);

namespace App\Listeners\Products;

use App\Events\Products\AllProductFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\Products\BuildProductBatchesJob;

/**
 * Listens for AllProductFulfilmentsCreated and dispatches BuildProductBatchesJob
 * to begin Phase 2.
 *
 * Parallel to AllFulfilmentsCreatedListener. That class is closed for
 * modification — this is a new listener for the product pipeline.
 *
 * This is the only Phase 1 → Phase 2 transition point for products.
 */
class AllProductFulfilmentsCreatedListener
{
    public function __construct(
        private readonly Logger $logger,
    )
    {
    }

    public function handle(AllProductFulfilmentsCreated $event): void
    {
        $run = $event->fulfilmentRun;

        dispatch(BuildProductBatchesJob::for((int)$run->id));

        $this->logger->info('AllProductFulfilmentsCreatedListener: BuildProductBatchesJob dispatched', [
            'run_id' => $run->id,
            'total_fulfilments' => $event->totalFulfilments,
        ]);
    }
}