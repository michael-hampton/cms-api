<?php

namespace App\Enums\Workflow;

enum WorkflowRunStatus: string
{
    case RUNNING = 'running';
    case BATCHING = 'batching';
    case EXPORTING = 'exporting';
    case COMPLETE = 'complete';
    case NO_DATA = 'no_data';
    case FAILED = 'failed';
    case STALLED = 'stalled';

    public function isFinal(): bool
    {
        return match ($this) {
            self::COMPLETE,
            self::NO_DATA,
            self::FAILED,
            self::STALLED => true,
            default => false,
        };
    }
}