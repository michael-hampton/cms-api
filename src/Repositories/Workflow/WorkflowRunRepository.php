<?php

namespace App\Repositories\Workflow;

use App\Models\WorkflowRun;
use App\Repositories\Repository;

class WorkflowRunRepository extends Repository
{
    protected function getModelClass(): string
    {
        return WorkflowRun::class;
    }
}