<?php

namespace App\DTO\Stripe;

/**
 * Input DTO for creating any Stripe subscription (standard or trial).
 * Schedule-based billing uses CreateStripeSubscriptionScheduleDto instead.
 */
final class CreateStripeSubscriptionDto
{
    public function __construct(
        public readonly string  $stripeCustomerId,
        public readonly string  $stripePriceId,
        public readonly int     $subscriptionId,
        public readonly int     $planId,
        public readonly int     $memberId,
        public readonly int     $siteId,
        public readonly ?int    $trialDays      = null,
        public readonly ?string $currency       = 'gbp',
        public readonly ?int    $voucherId      = null,
    ) {}
}
