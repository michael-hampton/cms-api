<?php

namespace App\Events\Subscriptions;

use App\Models\Subscription;

/**
 * Fired after a print subscription record is successfully linked to a
 * member account for the first time.
 *
 * Listeners can use this event to:
 *   - Grant digital access entitlements
 *   - Send a "subscription linked" confirmation email
 *   - Sync the record to a CRM or fulfilment system
 */
final class SubscriptionLinked
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly int          $memberId,
        public readonly int          $siteId,
    )
    {
    }
}