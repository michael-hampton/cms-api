<?php

namespace App\Services\Billing\Stripe;

use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;

class NullStripePriceGateway implements StripePriceGatewayInterface
{
    public function createRecurringPrice(
        string $stripeProductId,
        int    $amountCents,
        string $currency,
        string $interval,
    ): string
    {
        return 'price_test_' . uniqid();
    }
}