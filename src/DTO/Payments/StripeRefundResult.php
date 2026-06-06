<?php

namespace App\DTO\Payments;

/**
 * Normalised result returned by the Stripe refund gateway.
 *
 * Keeps Stripe types out of the service layer — callers work with this DTO,
 * never with raw Stripe objects.
 */
final class StripeRefundResult
{
    public function __construct(
        public readonly string $refundId,
        public readonly string $status,
        public readonly int    $amountCents,
        public readonly string $currency,
    ) {
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }
}