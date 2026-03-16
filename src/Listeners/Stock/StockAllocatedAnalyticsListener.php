<?php

namespace App\Listeners\Stock;

use App\Events\Stock\StockAllocated;
use App\Framework\Support\Logger;

/**
 * Records every stock allocation (confirmed or reserved) for analytics and audit.
 */
class StockAllocatedAnalyticsListener
{
    public function handle(StockAllocated $event): void
    {
        Logger::info('Stock allocated', [
            'model_class' => $event->modelClass,
            'model_id' => $event->modelId,
            'item_name' => $event->itemName,
            'quantity_allocated' => $event->quantityAllocated,
            'remaining_stock' => $event->remainingStock,
            'confirmed' => $event->confirmed,
            'reservation_id' => $event->reservationId,
        ]);

        // TODO: push to analytics pipeline (e.g. Segment, internal warehouse)
    }
}