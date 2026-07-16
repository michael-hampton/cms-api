<?php

declare(strict_types=1);

namespace App\DTO\Stripe;

/**
 * Parsed representation of a Stripe invoice.* webhook event.
 *
 * Keeps the handler free of raw Stripe object traversal.
 * All fields are nullable — Stripe does not guarantee every field
 * is present on every invoice event variant.
 */
final class StripeInvoiceEvent
{
    public function __construct(
        public readonly string  $type,
        public readonly string  $invoiceId,
        public readonly ?string $stripeSubscriptionId,
        public readonly ?string $paymentIntentId,
        public readonly int     $amountPaid,        // cents
        public readonly string  $currency,
        public readonly ?int    $periodStart,        // unix timestamp
        public readonly ?int    $periodEnd,          // unix timestamp
        public readonly ?string $failureReason,
        public readonly ?string $failureCode,
        public readonly ?string $hostedInvoiceUrl = null,
        public readonly ?string $rawPayload = null,
    )
    {
    }

    public function paidAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    public function currentPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart
            ? new \DateTimeImmutable('@' . $this->periodStart)
            : null;
    }

    public function currentPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd
            ? new \DateTimeImmutable('@' . $this->periodEnd)
            : null;
    }
}