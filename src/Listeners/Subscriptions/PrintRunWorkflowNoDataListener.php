<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\PrintRunWorkflowNoData;
use App\Framework\Support\Logger;

/**
 * A print run workflow completing with zero eligible issue deliveries is
 * usually routine (nothing was due), but can also signal a scheduling or
 * cut-off configuration problem that silently produces no print orders.
 * PrintRunWorkflow already logs this at info level as it happens; this
 * listener raises a warning-level entry specifically for the "workflow
 * completed with no data" outcome so it's easy to alert on separately from
 * the routine per-step info logging.
 */
class PrintRunWorkflowNoDataListener
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function handle(PrintRunWorkflowNoData $event): void
    {
        $workflowRun = $event->workflowRun;

        $this->logger->warning('PrintRunWorkflowNoDataListener: workflow completed with no eligible issue deliveries', [
            'workflow_run_id' => $workflowRun->id,
            'workflow_type' => $workflowRun->workflow_type,
            'input' => $workflowRun->input,
        ]);
    }
}
