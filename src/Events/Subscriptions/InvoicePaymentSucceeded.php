<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\Payment;
use App\Models\Subscription;

// ---------------------------------------------------------------------------
// InvoicePaymentSucceeded
// ---------------------------------------------------------------------------
// Fired after invoice.payment_succeeded is fully processed:
//   - payment record created/updated
//   - subscription billing fields updated
// Listeners are responsible for access grants, notifications, etc.
// ---------------------------------------------------------------------------

final class InvoicePaymentSucceeded
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly Payment      $payment,
    )
    {
    }
}