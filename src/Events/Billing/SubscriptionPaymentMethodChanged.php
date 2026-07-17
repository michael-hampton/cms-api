<?php

namespace App\Events\Billing;

/**
 * Fired when a specific subscription's payment method changes - either
 * because the member explicitly picked a different saved card on the
 * manage-subscription screen, or because a card was replaced and its
 * subscriptions were moved onto the new one. Listeners: analytics/audit
 * logging.
 */
class SubscriptionPaymentMethodChanged
{
    public function __construct(
        public readonly int $memberId,
        public readonly int $subscriptionId,
        public readonly string $paymentMethodId,
        public readonly string $source,
    ) {
    }
}