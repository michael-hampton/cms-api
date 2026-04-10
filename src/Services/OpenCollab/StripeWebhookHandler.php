<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\StripeEvent;

class StripeWebhookHandler
{
    public function __construct(private readonly ArticleAccessService $accessService)
    {
    }

    public function handle(StripeEvent $event): void
    {
        match ($event->type) {
            'payment_intent.succeeded'
            => $this->accessService->grantAccessFromPayment($event->paymentIntentId),

            'payment_intent.payment_failed'
            => $this->accessService->recordPaymentFailure($event->paymentIntentId),

            default => null,
        };
    }
}