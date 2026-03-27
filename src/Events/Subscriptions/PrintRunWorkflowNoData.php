<?php

namespace App\Events\Subscriptions;

use App\Models\WorkflowRun;

class PrintRunWorkflowNoData
{

    /**
     * @param \App\Models\Model $workflowRun
     */
    public function __construct(public WorkflowRun $workflowRun)
    {
    }
}