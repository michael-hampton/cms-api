<?php

namespace App\Events\Subscriptions;

class PaymentSucceeded
{
    public function __construct(
        public readonly int    $subscriptionId,
        public readonly int    $paymentId,
        public readonly int    $amountCents,
        public readonly string $currency,
    )
    {
    }
}