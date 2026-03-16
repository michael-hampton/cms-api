<?php

namespace App\Listeners\Stock;

use App\Events\Stock\StockLow;
use App\Framework\Support\Logger;

/**
 * Sends an operational alert whenever a stockable item falls to or below
 * the low-stock threshold after an allocation.
 *
 * Extend this listener to send email / Slack / PagerDuty notifications.
 */
class StockLowAlertListener
{
    public function handle(StockLow $event): void
    {
        Logger::warning('Low stock threshold reached', [
            'model_class' => $event->modelClass,
            'model_id' => $event->modelId,
            'item_name' => $event->itemName,
            'remaining_stock' => $event->remainingStock,
            'threshold' => $event->threshold,
        ]);

        // TODO: dispatch notification to operations channel
        //       e.g. Notification::route('slack', config('stock.slack_webhook'))
        //                        ->notify(new LowStockNotification($event));
    }
}