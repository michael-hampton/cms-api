<?php

namespace App\Services\Billing\PaymentProviders;

use Stripe\StripeClient;

class StripePaymentIntentGateway implements PaymentIntentGateway
{
    public function __construct(private StripeClient $stripe)
    {
    }

    public function create(array $data)
    {
        return $this->stripe->paymentIntents->create($data);
    }

    public function retrieve(mixed $stripe_payment_intent_id)
    {
        return $this->stripe->paymentIntents->retrieve($stripe_payment_intent_id);
    }
}