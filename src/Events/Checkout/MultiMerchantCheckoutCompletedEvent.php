<?php

namespace App\Events\Checkout;

class MultiMerchantCheckoutCompletedEvent
{
    public function __construct(
        public readonly string $checkoutId,
        public readonly array  $orders
    )
    {
    }
}