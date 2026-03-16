<?php

namespace App\Listeners\Stock;

use App\Events\Stock\StockConfirmed;
use App\Framework\Support\Logger;

/**
 * Records the moment a subscription stock reservation is confirmed by payment.
 */
class StockConfirmedAnalyticsListener
{
    public function handle(StockConfirmed $event): void
    {
        Logger::info('Stock reservation confirmed', [
            'model_class' => $event->modelClass,
            'model_id' => $event->modelId,
            'item_name' => $event->itemName,
            'quantity_confirmed' => $event->quantityConfirmed,
            'remaining_stock' => $event->remainingStock,
            'reservation_id' => $event->reservationId,
        ]);

        // TODO: push to analytics pipeline
    }
}