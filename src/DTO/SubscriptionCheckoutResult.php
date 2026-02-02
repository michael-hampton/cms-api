<?php

namespace App\DTO;

class SubscriptionCheckoutResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly ?int    $subscriptionId = null,
        public readonly ?string $message = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $paymentIntentId = null,
        public readonly ?array  $errors = null
    )
    {
    }

    public static function success(
        int     $subscriptionId,
        ?string $redirectUrl = null,
        ?string $paymentIntentId = null
    ): self
    {
        return new self(
            success: true,
            subscriptionId: $subscriptionId,
            message: 'Subscription created successfully',
            redirectUrl: $redirectUrl,
            paymentIntentId: $paymentIntentId
        );
    }

    public static function failure(string $message, ?array $errors = null): self
    {
        return new self(
            success: false,
            message: $message,
            errors: $errors
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'subscription_id' => $this->subscriptionId,
            'message' => $this->message,
            'redirect_url' => $this->redirectUrl,
            'payment_intent_id' => $this->paymentIntentId,
            'errors' => $this->errors
        ];
    }
}