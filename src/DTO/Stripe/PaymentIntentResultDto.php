<?php

namespace App\DTO\Stripe;

/**
 * Normalised result from all PaymentIntent gateway calls.
 * No raw Stripe SDK objects escape beyond the gateway boundary.
 */
class PaymentIntentResultDto
{
    public function __construct(
        public readonly bool    $success,
        public readonly ?string $paymentIntentId = null,
        public readonly ?string $clientSecret    = null,
        public readonly ?string $status          = null,
        public readonly ?string $customerId      = null,
        public readonly ?string $paymentMethodId = null,
        public readonly ?int    $amountCents     = null,
        public readonly ?string $currency        = null,
        public readonly ?string $errorMessage    = null,
        public readonly ?string $errorCode       = null,
    ) {}

    public function requiresAction(): bool
    {
        return $this->status === 'requires_action';
    }

    /**
     * Convert to the legacy array shape that existing callers expect.
     * Use this only during the migration period; new callers should use
     * the typed properties directly.
     */
    public function toLegacyArray(): array
    {
        if (!$this->success) {
            return [
                'success' => false,
                'message' => $this->errorMessage ?? 'Payment failed',
            ];
        }

        return [
            'success'          => true,
            'payment_intent_id' => $this->paymentIntentId,
            'client_secret'    => $this->clientSecret,
            'status'           => $this->status,
            'customer_id'      => $this->customerId,
            'requires_action'  => $this->requiresAction(),
        ];
    }
}
