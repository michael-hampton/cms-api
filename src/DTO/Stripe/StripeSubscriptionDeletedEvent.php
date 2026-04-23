<?php

declare(strict_types=1);

namespace App\DTO\Stripe;

/**
 * Parsed representation of a customer.subscription.deleted webhook event.
 */
final class StripeSubscriptionDeletedEvent
{
    public function __construct(
        public readonly string $stripeSubscriptionId,
        public readonly string $stripeStatus,
        public readonly ?int   $canceledAt,          // unix timestamp from Stripe
        public readonly ?int   $currentPeriodEnd,    // unix timestamp — used for grace-until access
    )
    {
    }

    public function cancelledAt(): \DateTimeImmutable
    {
        return $this->canceledAt
            ? new \DateTimeImmutable('@' . $this->canceledAt)
            : new \DateTimeImmutable();
    }

    public function accessUntil(): ?\DateTimeImmutable
    {
        return $this->currentPeriodEnd
            ? new \DateTimeImmutable('@' . $this->currentPeriodEnd)
            : null;
    }
}