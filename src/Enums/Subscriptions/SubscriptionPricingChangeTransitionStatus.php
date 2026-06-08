<?php

namespace App\Enums\Subscriptions;

enum SubscriptionPricingChangeTransitionStatus: string
{
    case Pending = 'pending';
    case OldSubscriptionCancelled = 'old_subscription_cancelled';
    case NewSubscriptionCreated = 'new_subscription_created';
    case ItdGenerated = 'itd_generated';
    case Completed = 'completed';
    case Failed = 'failed';
}