<?php

namespace App\Enums\Subscriptions;

enum SubscriptionIssueFulfilmentStatus: string
{
    case SCHEDULED = 'scheduled';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case SUPERSEDED = 'superseded';
}
