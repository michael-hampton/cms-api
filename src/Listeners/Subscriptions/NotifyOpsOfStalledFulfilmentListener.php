<?php

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\PrintFulfilmentStalled;
use App\Framework\Support\Logger;

class NotifyOpsOfStalledFulfilmentListener
{
    public function __construct(
        private readonly Logger $logger,
        // Inject your notification gateway here, e.g.:
        // private readonly OpsNotificationGateway $gateway,
    )
    {
    }

    public function handle(PrintFulfilmentStalled $event): void
    {
        $printRun = $event->printRun;

        $message = sprintf(
            '[PRINT PIPELINE STALL] PrintRun #%d has been stuck in Fulfilling for %d minutes. '
            . '%d of %d chunks completed (%d missing). '
            . 'Manual investigation required — do NOT re-dispatch without checking whether '
            . 'original chunk jobs are still in-flight.',
            $printRun->id,
            $event->monitorDelayMinutes,
            $event->completedChunks,
            $event->totalChunks,
            $event->missingChunks(),
        );

        $this->logger->error('PrintFulfilmentStalled', [
            'print_run_id' => $printRun->id,
            'completed_chunks' => $event->completedChunks,
            'total_chunks' => $event->totalChunks,
            'missing_chunks' => $event->missingChunks(),
            'monitor_delay_mins' => $event->monitorDelayMinutes,
        ]);

        // Replace with your real notification call, e.g.:
        // $this->gateway->sendOpsAlert($message, context: [
        //     'print_run_id'     => $printRun->id,
        //     'missing_chunks'   => $event->missingChunks(),
        //     'issue_delivery_id'=> $printRun->issue_delivery_id,
        // ]);

        // For now, the logger call above ensures the event is not fired silently
        // into the void. Replace with a real alert before going to production.
    }
}