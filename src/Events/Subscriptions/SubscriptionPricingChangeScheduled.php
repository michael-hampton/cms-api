<?php

namespace App\Events\Subscriptions;

use App\Models\SubscriptionPricingChange;

/**
 * Fired when an admin schedules a price change for a subscription plan.
 *
 * The listener is responsible for dispatching per-subscriber notification jobs.
 * This event must not be fired unless the pricing change record has been
 * persisted successfully.
 */
class SubscriptionPricingChangeScheduled
{
    public function __construct(
        public readonly SubscriptionPricingChange $pricingChange,
    )
    {
    }
}