<?php

namespace App\DTO\Stripe;

/**
 * Input DTO for PaymentIntent creation.
 * amountCents is in the smallest currency unit (pence, cents).
 */
final class CreatePaymentIntentDto
{
    public function __construct(
        public readonly int     $amountCents,
        public readonly string  $currency,
        public readonly array   $metadata        = [],
        public readonly ?string $stripeCustomerId = null,
    ) {}
}