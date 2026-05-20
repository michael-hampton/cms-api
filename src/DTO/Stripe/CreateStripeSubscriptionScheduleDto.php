<?php

namespace App\DTO\Stripe;

/**
 * Input DTO for creating a Stripe Subscription Schedule (intro pricing).
 *
 * Phases are intentionally not included here — the factory builds them
 * from the pricing strategy. This DTO carries only the data needed to
 * identify the customer, prices, and metadata.
 */
final class CreateStripeSubscriptionScheduleDto
{
    public function __construct(
        public readonly string $stripeCustomerId,
        public readonly string $introPriceId,
        public readonly string $recurringPriceId,
        public readonly int    $introCycles,
        public readonly int    $subscriptionId,
        public readonly int    $planId,
        public readonly int    $memberId,
        public readonly int    $siteId,
        public readonly ?int   $trialDays = null,
        public readonly ?string $currency = 'gbp',
        public readonly ?int   $voucherId = null,
    ) {}
}
