<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\Subscription;

// ---------------------------------------------------------------------------
// InvoiceUpcoming
// ---------------------------------------------------------------------------
// Fired when Stripe's invoice.upcoming preview event arrives for a known
// subscription. Purely a notification trigger — no billing state changes,
// no payment record (Stripe hasn't attempted the charge yet).
// ---------------------------------------------------------------------------

final class InvoiceUpcoming
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly int          $amountDue,
        public readonly string       $currency,
    ) {
    }
}
