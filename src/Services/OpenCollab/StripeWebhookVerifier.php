<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\StripeEvent;
use App\Framework\Http\Request;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookVerifier
{
    public function __construct(
        private StripeClient $stripe,
        private string       $secret
    )
    {
    }

    public function verify(Request $request): StripeEvent
    {
        $payload = $request->getContent(); // IMPORTANT: raw body
        $sigHeader = $request->header('Stripe-Signature', '');

        $event = Webhook::constructEvent($payload, $sigHeader, $this->secret);

        return new StripeEvent(
            type: $event->type,
            paymentIntentId: $event->data->object->id ?? null
        );
    }
}