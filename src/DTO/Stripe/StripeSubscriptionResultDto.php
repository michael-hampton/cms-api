<?php

namespace App\DTO\Stripe;

/**
 * Normalised result returned by both subscription gateways.
 * No raw Stripe SDK objects escape past the gateway boundary.
 */
final class StripeSubscriptionResultDto
{
    public function __construct(
        public readonly string  $stripeSubscriptionId,
        public readonly ?string $stripeScheduleId,
        public readonly string  $status,
        public readonly ?string $stripeCustomerId,
        public readonly ?int    $currentPeriodStart,
        public readonly ?int    $currentPeriodEnd,
        public readonly ?string $latestInvoiceId,
        public readonly ?string $paymentIntentId,
        public readonly ?string $paymentIntentClientSecret,
        public readonly bool    $requiresAction,
        public readonly ?string $stripeSubscriptionItemId = null,
    ) {}
}
