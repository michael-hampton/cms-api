<?php

namespace App\Events\Subscriptions;

use App\Models\Subscription;

/**
 * Fired when a trial conversion payment is declined.  The subscription has
 * already been set to EXPIRED before this event is emitted.
 *
 * Listeners should notify the member and provide a reactivation link.
 */
final class TrialConversionFailedEvent
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly string       $reason,
    )
    {
    }
}