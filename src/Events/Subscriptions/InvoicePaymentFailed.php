<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\Payment;
use App\Models\Subscription;

// ---------------------------------------------------------------------------
// InvoicePaymentFailed
// ---------------------------------------------------------------------------
// Fired after invoice.payment_failed is fully processed:
//   - payment record created/updated with failure details
//   - subscription moved to PAST_DUE
// Listeners handle grace-period logic, retry notifications, etc.
// Note: PAST_DUE ≠ cancelled. Stripe retries automatically.
// ---------------------------------------------------------------------------

final class InvoicePaymentFailed
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly Payment      $payment,
        public readonly ?string      $failureReason,
        public readonly ?string      $failureCode,
    )
    {
    }
}