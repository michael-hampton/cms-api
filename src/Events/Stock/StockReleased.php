<?php

namespace App\Events\Stock;

/**
 * Fired immediately after stock has been incremented (released) for any stockable item.
 *
 * Listeners: StockReleasedAnalyticsListener.
 */
class StockReleased
{
    public function __construct(
        public readonly string $modelClass,
        public readonly int    $modelId,
        public readonly string $itemName,
        public readonly int    $quantityReleased,
        public readonly int    $remainingStock,
    )
    {
    }
}