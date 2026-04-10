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
}