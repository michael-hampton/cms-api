<?php

namespace App\Services\Billing\Stripe;

use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;
use Stripe\StripeClient;

class StripePriceGateway implements StripePriceGatewayInterface
{
    private StripeClient $stripe;

    public function __construct(?StripeClient $stripeClient = null)
    {
        if ($stripeClient) {
            $this->stripe = $stripeClient;
        } else {
            $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
            $this->stripe = new StripeClient($secretKey);
        }
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