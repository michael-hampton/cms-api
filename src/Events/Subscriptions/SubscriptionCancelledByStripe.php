<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\Subscription;

// ---------------------------------------------------------------------------
// SubscriptionCancelledByStripe
// ---------------------------------------------------------------------------
// Fired after customer.subscription.deleted is processed:
//   - subscription marked as CANCELLED in your DB
// Listeners handle access revocation, member notifications, etc.
//
// $accessUntil: the end of the last paid period — listeners that implement
// "allow access until period end" should use this, not the current time.
// ---------------------------------------------------------------------------

final class SubscriptionCancelledByStripe
{
    public function __construct(
        public readonly Subscription        $subscription,
        public readonly \DateTimeImmutable  $cancelledAt,
        public readonly ?\DateTimeImmutable $accessUntil,
    )
    {
    }
}