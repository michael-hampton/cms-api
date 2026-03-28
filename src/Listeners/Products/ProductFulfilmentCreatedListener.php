<?php

declare(strict_types=1);

namespace App\Listeners\Products;

use App\Events\Products\ProductFulfilmentCreated;
use App\Framework\Support\Logger;

/**
 * Handles side effects after a ProductFulfilment record is persisted.
 *
 * Current responsibilities:
 *   - Observability logging (always).
 *
 * Future: add supplier notification here when the use case is confirmed.
 * This listener satisfies the "every event must have a listener" contract
 * for ProductFulfilmentCreated.
 */
class ProductFulfilmentCreatedListener
{
    public function __construct(
        private readonly Logger $logger,
    )
    {
    }

    public function handle(ProductFulfilmentCreated $event): void
    {
        $f = $event->fulfilment;

        $this->logger->info('ProductFulfilmentCreatedListener: fulfilment created', [
            'fulfilment_id' => $f->id,
            'order_id' => $f->order_id,
            'order_line_id' => $f->order_line_id,
            'sku' => $f->sku,
            'territory_id' => $f->territory_id,
            'batch_id' => $f->product_batch_id,
        ]);
    }
}