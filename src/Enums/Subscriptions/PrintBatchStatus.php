<?php

namespace App\Enums\Subscriptions;

enum PrintBatchStatus: string
{
    case QUEUED = 'queued';
    case BATCH_EXPORTING = 'batch_exporting';
    case BATCH_EXPORTED = 'batch_exported';
    case BATCH_FAILED = 'batch_failed';
}