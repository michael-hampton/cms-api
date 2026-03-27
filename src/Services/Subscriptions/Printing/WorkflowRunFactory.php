<?php

namespace App\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\PrintRunWorkflowInput;
use App\Models\Model;
use App\Models\WorkflowRun;

class WorkflowRunFactory
{
    public function create(PrintRunWorkflowInput $input): Model
    {
        // Original static call replaced
        return WorkflowRun::start($input);
    }
}