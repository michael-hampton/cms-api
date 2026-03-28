<?php

declare(strict_types=1);

namespace App\Listeners\Products;

use App\Events\Products\ProductFulfilmentStalled;
use App\Framework\Support\Logger;

/**
 * Sends an ops alert when ProductFulfilmentMonitorJob detects a stall.
 *
 * Satisfies the "every event must have a listener" contract for
 * ProductFulfilmentStalled. Wire your notification gateway here
 * (Slack, PagerDuty, email) before going to production.
 */
class NotifyOpsOfStalledProductFulfilmentListener
{
    public function __construct(
        private readonly Logger $logger,
        // private readonly OpsNotificationGateway $gateway,
    )
    {
    }

    public function handle(ProductFulfilmentStalled $event): void
    {
        $run = $event->fulfilmentRun;

        $this->logger->error('NotifyOpsOfStalledProductFulfilmentListener: stall alert', [
            'run_id' => $run->id,
            'completed_chunks' => $event->completedChunks,
            'total_chunks' => $event->totalChunks,
            'missing_chunks' => $event->missingChunks(),
            'monitor_delay' => $event->monitorDelayMinutes,
        ]);

        // Replace with: $this->gateway->sendOpsAlert(
        //     "[PRODUCT PIPELINE STALL] Run #{$run->id} — {$event->missingChunks()} chunks missing.",
        //     context: [...],
        // );
    }
}