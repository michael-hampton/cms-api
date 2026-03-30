<?php

namespace App\Enums\Workflow;

enum WorkflowStageStatus: string
{
    case SUCCEEDED = 'succeeded';
    case PARTIAL = 'partial';
    case FAILED = 'failed';
}