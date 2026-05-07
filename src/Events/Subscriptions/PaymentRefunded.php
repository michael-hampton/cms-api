<?php

namespace App\Events\Subscriptions;

class PaymentRefunded
{
    public function __construct(
        public readonly int    $subscriptionId,
        public readonly int    $paymentId,
        public readonly int    $amountCents,
        public readonly string $currency,
        public readonly string $reason,
    )
    {
    }
}