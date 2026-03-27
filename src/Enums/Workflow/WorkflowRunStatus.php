<?php

namespace App\Enums\Workflow;

enum WorkflowRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETE = 'complete';
    case FAILED = 'failed';
    case NO_DATA = 'no_data';
}