<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\Subscription;

// ---------------------------------------------------------------------------
// SubscriptionFirstIssueDelivered
// ---------------------------------------------------------------------------
// Fired once, the moment a subscription's delivered-issue count transitions
// from 0 to 1 (see DeliverIssueDeliveryJob). Not a schedule-based
// communication trigger — this is a one-off event, so it does not go
// through SubscriptionCommunicationDueResolver/CandidateResolver.
// ---------------------------------------------------------------------------

final class SubscriptionFirstIssueDelivered
{
    public function __construct(
        public readonly Subscription $subscription,
    ) {
    }
}
