<?php

namespace App\Events\Stock;

/**
 * Fired after an allocation leaves the remaining stock at or below the threshold.
 *
 * Listeners: StockLowAlertListener (sends email/Slack alert to operations team).
 */
class StockLow
{
    public function __construct(
        public readonly string $modelClass,
        public readonly int    $modelId,
        public readonly string $itemName,
        public readonly int    $remainingStock,
        public readonly int    $threshold,
    )
    {
    }
}