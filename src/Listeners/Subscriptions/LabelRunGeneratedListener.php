<?php

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\LabelRunGenerated;
use App\Framework\Support\Logger;

class LabelRunGeneratedListener
{
    public function __construct(
        private readonly Logger $logger,
    )
    {
    }

    public function handle(LabelRunGenerated $event): void
    {
        $labelRun = $event->labelRun;

        $this->logger->info('LabelRunGeneratedListener: label run complete', [
            'label_run_id' => $labelRun->id,
            'print_batch_id' => $labelRun->print_batch_id,
            'issues_delivered_id' => $labelRun->issues_delivered_id,
            'format' => $labelRun->format,
            'file_path' => $labelRun->file_path,
            'transport' => $labelRun->transport_identifier,
        ]);

        // Future: check if all LabelRuns for this batch are complete,
        // and if so fire a BatchLabelsComplete event for supplier notification.
    }
}