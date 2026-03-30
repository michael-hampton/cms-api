<?php

namespace App\Services\Workflow;

use App\Enums\Workflow\WorkflowRunStatus;
use App\Framework\Support\Logger;
use App\Models\PrintRun;

class WorkflowRunRecorderFactory
{
    public function __construct(
        private readonly Logger $logger,
    )
    {
    }

    public function forPrintRun(PrintRun $printRun, string $stage, WorkflowRunStatus $nextStatus): WorkflowRunRecorder
    {
        return new WorkflowRunRecorder(
            workflowRunId: $printRun->workflow_run_id,
            stage: $stage,
            nextStatus: $nextStatus,
            logger: $this->logger,
        );
    }

    public function forId(?int $workflowRunId, string $stage, WorkflowRunStatus $nextStatus): WorkflowRunRecorder
    {
        return new WorkflowRunRecorder(
            workflowRunId: $workflowRunId,
            stage: $stage,
            nextStatus: $nextStatus,
            logger: $this->logger,
        );
    }
}