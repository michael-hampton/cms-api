<?php

namespace App\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\PrintRunWorkflowInput;
use App\Models\Model;

/**
 * Contract for the print run orchestration workflow.
 *
 * Implementations must be stateless — all context lives in PrintRunWorkflowInput.
 * The returned WorkflowRun is the audit record for the execution.
 */
interface PrintRunWorkflowInterface
{
    /**
     * Execute the print run workflow for the given input.
     *
     * Returns the WorkflowRun audit record regardless of outcome so callers
     * (jobs, CLI commands, tests) can inspect the result without catching.
     *
     * Throws only on unrecoverable infrastructure failure (e.g. DB unavailable).
     * Domain failures (no issues, unknown driver) are recorded on the WorkflowRun
     * and returned — they do not throw.
     */
    public function execute(PrintRunWorkflowInput $input): Model;
}