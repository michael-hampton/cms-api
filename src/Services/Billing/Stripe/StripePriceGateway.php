<?php

namespace App\Services\Billing\Stripe;

use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;
use Stripe\StripeClient;

class StripePriceGateway implements StripePriceGatewayInterface
{
    public function __construct(private readonly StripeClient $stripe)
    {
    }

    public function createRecurringPrice(
        string $stripeProductId,
        int    $amountCents,
        string $currency,
        string $interval
    ): string
    {
        $price = $this->stripe->prices->create([
            'product' => $stripeProductId,
            'unit_amount' => $amountCents,
            'currency' => strtolower($currency),
            'recurring' => ['interval' => $interval],
        ]);

        return $price->id;
    }
}