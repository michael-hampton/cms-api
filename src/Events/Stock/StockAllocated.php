<?php

namespace App\Events\Stock;

/**
 * Fired immediately after stock has been decremented for any stockable item.
 *
 * `confirmed = true`  → physical product path; stock is finalised inside the
 *                        order-creation transaction.
 * `confirmed = false` → subscription reservation path (Phase 1); payment has
 *                        not yet succeeded. StockConfirmed fires on Phase 3 success,
 *                        StockReleased fires on Phase 3 failure.
 *
 * Listeners: StockLowNotificationListener (checks threshold and dispatches StockLow).
 */
class StockAllocated
{
    public function __construct(
        /** Fully-qualified model class name, e.g. Product::class */
        public readonly string $modelClass,
        public readonly int    $modelId,
        public readonly string $itemName,
        public readonly int    $quantityAllocated,
        public readonly int    $remainingStock,
        /**
         * True  → allocation is finalised (physical product inside committed transaction).
         * False → allocation is a reservation pending payment confirmation.
         */
        public readonly bool   $confirmed,
        /**
         * Non-null only when confirmed = false.
         * Passed back to StockService::confirm() / StockService::release().
         */
        public readonly ?int   $reservationId = null,
    )
    {
    }
}