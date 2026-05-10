<?php

namespace App\Enums\Subscriptions;

enum FulfilmentReplacementStatus: string
{
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case SENT = 'sent';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}