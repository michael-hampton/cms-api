<?php

namespace App\Enums\Subscriptions;

enum IssueDeliveryStatus: string
{
    case ACTIVE = 'active';
    case SCHEDULED = 'scheduled';
    case DISPATCHED = 'dispatched';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}