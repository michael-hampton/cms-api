<?php

namespace App\Events\Subscriptions;

use App\Models\Subscription;

/**
 * Fired after a TRIALING subscription is successfully charged and transitioned
 * to ACTIVE.  Listeners should send the member a "welcome to paid" email,
 * provision any gated content, etc.
 *
 * This is a real side-effect event — do NOT create it "for future use".
 */
final class TrialConvertedEvent
{
    public function __construct(
        public readonly Subscription $subscription,
    )
    {
    }
}