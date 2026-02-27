<?php

namespace App\Enums\Subscriptions;

enum SubscriptionPricingChangeStatus: string
{
    case Scheduled = 'scheduled';   // Created, notices not yet sent
    case Notified = 'notified';    // All affected subscribers emailed
    case Applied = 'applied';     // New price is now live
    case Cancelled = 'cancelled';   // Withdrawn before effective date
}