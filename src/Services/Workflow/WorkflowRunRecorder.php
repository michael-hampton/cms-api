<?php

namespace App\Services\Workflow;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Enums\Workflow\WorkflowStageStatus;
use App\Framework\Support\Logger;
use App\Models\WorkflowRun;

class WorkflowRunRecorder
{
    public function __construct(
        private readonly ?int              $workflowRunId,
        private readonly string            $stage,
        private readonly WorkflowRunStatus $nextStatus,
        private readonly Logger            $logger,
    )
    {
    }

    public function record(WorkflowStageResult $result): void
    {
        if (!$this->workflowRunId) {
            return;
        }

        try {
            $workflowRun = WorkflowRun::find($this->workflowRunId);

            if (!$workflowRun) {
                return;
            }

            $this->applyResult($workflowRun, $result);

        } catch (\Throwable $e) {
            $this->logger->warning('WorkflowRunRecorder: failed to record stage result', [
                'workflow_run_id' => $this->workflowRunId,
                'stage' => $this->stage,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function applyResult(WorkflowRun $workflowRun, WorkflowStageResult $result): void
    {
        $updatedSummary = array_merge($workflowRun->summary ?? [], [
            $this->stage => array_filter([
                'status' => $result->status->value,
                'summary' => $result->summary ?: null,
                'error' => $result->error,
                'recorded_at' => now_datetime()->format('Y-m-d H:i:s'),
            ]),
        ]);

        $workflowRunStatus = match ($result->status) {
            WorkflowStageStatus::FAILED => WorkflowRunStatus::FAILED,
            WorkflowStageStatus::SUCCEEDED => $this->nextStatus,
            WorkflowStageStatus::PARTIAL => WorkflowRunStatus::from($workflowRun->status),
        };

        $workflowRun->recordStage($workflowRunStatus, $updatedSummary);
    }
}