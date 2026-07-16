<?php

namespace App\Events\Billing;

/**
 * Fired after a payment method has been successfully attached to a
 * member's Stripe customer. Listeners: analytics/audit logging.
 */
class PaymentMethodAdded
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $paymentMethodId,
        public readonly bool $setAsDefault,
        public readonly string $source,
    ) {
    }
}
