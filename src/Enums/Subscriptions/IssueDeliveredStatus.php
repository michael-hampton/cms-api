<?php

namespace App\Enums\Subscriptions;

enum IssueDeliveredStatus: string
{
    case SCHEDULED = 'scheduled';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
}