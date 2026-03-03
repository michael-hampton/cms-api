<?php

namespace App\Services\Billing\Stripe\Contracts;

interface StripePriceGatewayInterface
{
    /**
     * Create a recurring price in Stripe and return the stripe_price_id.
     *
     * @param string $stripeProductId The Stripe product this price belongs to.
     * @param int $amountCents Price in the smallest currency unit (e.g. pence/cents).
     * @param string $currency ISO 4217 currency code, lowercase (e.g. 'gbp').
     * @param string $interval Billing interval: 'month' or 'year'.
     */
    public function createRecurringPrice(
        string $stripeProductId,
        int    $amountCents,
        string $currency,
        string $interval
    ): string;
}