<?php

namespace App\Events\Billing;

/**
 * Fired after a payment method has been successfully detached from a
 * member's Stripe customer. Listeners: analytics/audit logging.
 */
class PaymentMethodRemoved
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $paymentMethodId,
        public readonly string $source,
    ) {
    }
}
