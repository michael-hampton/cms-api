<?php

namespace App\Events\Subscriptions;

use App\Models\WorkflowRun;

class PrintRunWorkflowCompleted
{

    /**
     * @param Model $workflowRun
     */
    public function __construct(public WorkflowRun $workflowRun)
    {
    }
}