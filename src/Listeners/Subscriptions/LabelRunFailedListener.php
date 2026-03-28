<?php

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\LabelRunFailed;
use App\Framework\Support\Logger;

class LabelRunFailedListener
{
    private const ALERT_THRESHOLD = 3;

    public function __construct(
        private readonly Logger $logger,
        // private readonly OpsNotificationGateway $gateway,
    )
    {
    }

    public function handle(LabelRunFailed $event): void
    {
        $labelRun = $event->labelRun;

        $this->logger->error('LabelRunFailedListener: label generation failed', [
            'label_run_id' => $labelRun->id,
            'print_batch_id' => $labelRun->print_batch_id,
            'issues_delivered_id' => $labelRun->issues_delivered_id,
            'attempt_count' => $labelRun->attempt_count,
            'reason' => $event->reason,
        ]);

        // Alert ops if the run has reached the max attempt threshold.
        // The queue's retry mechanism handles individual retries; this is the
        // dead-letter notification.
        if ($labelRun->attempt_count >= self::ALERT_THRESHOLD) {
            $this->logger->critical('LabelRunFailedListener: label run exhausted retries — manual intervention required', [
                'label_run_id' => $labelRun->id,
                'print_batch_id' => $labelRun->print_batch_id,
                'reason' => $event->reason,
            ]);

            // $this->gateway->sendOpsAlert(...);
        }
    }
}