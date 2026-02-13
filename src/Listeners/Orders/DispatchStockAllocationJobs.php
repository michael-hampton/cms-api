<?php

namespace App\Listeners\Orders;

use App\Events\Orders\ProductStockUpdated;
use App\Jobs\Orders\AllocatePreorderStockJob;
use App\Jobs\Orders\SendBackInStockAlertsJob;

class DispatchStockAllocationJobs
{
    public function handle(ProductStockUpdated $event): void
    {
        // Only process when stock increases
        if ($event->newStock <= $event->oldStock) {
            return;
        }

        $job = AllocatePreorderStockJob::for();

        // Dispatch allocation job
        dispatch($job, $event->product->id);

        // Dispatch alert job if stock went from 0 to >0
        if ($event->oldStock === 0 && $event->newStock > 0) {
            $job2 = SendBackInStockAlertsJob::for();
            dispatch($job2, $event->product->id);
        }
    }
}