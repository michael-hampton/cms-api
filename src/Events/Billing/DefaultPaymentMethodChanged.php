<?php

namespace App\Events\Billing;

/**
 * Fired after a member's default (renewal) payment method changes.
 * Listeners: analytics/audit logging.
 */
class DefaultPaymentMethodChanged
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $paymentMethodId,
        public readonly string $source,
    ) {
    }
}
