<?php

namespace App\Events\Stock;

/**
 * Fired after a previously-reserved stock allocation is confirmed by successful payment.
 *
 * Only relevant to the subscription checkout path where stock is decremented
 * optimistically in Phase 1 and confirmed in Phase 3.
 *
 * Listeners: StockConfirmedAnalyticsListener.
 */
class StockConfirmed
{
    public function __construct(
        public readonly string $modelClass,
        public readonly int    $modelId,
        public readonly string $itemName,
        public readonly int    $quantityConfirmed,
        public readonly int    $remainingStock,
        /** The in-memory reservation ID returned by StockService::reserve(). */
        public readonly int    $reservationId,
    )
    {
    }
}