<?php

namespace App\Services\Billing\PaymentProviders;

use Stripe\StripeClient;

class StripePaymentIntentGateway implements PaymentIntentGateway
{
    private readonly StripeClient $stripe;

    public function __construct()
    {
        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
        $this->stripe = new StripeClient($secretKey);
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