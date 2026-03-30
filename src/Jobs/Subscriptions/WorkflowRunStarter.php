<?php

namespace App\Jobs\Subscriptions;

use App\Models\WorkflowRun;

class WorkflowRunStarter
{
    public function start(string $workflowClass, array $input): WorkflowRun
    {
        return WorkflowRun::start($workflowClass, $input);
    }
}