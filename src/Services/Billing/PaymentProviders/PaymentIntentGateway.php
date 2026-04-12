<?php

namespace App\Services\Billing\PaymentProviders;

interface PaymentIntentGateway
{
    public function create(array $data);

    public function retrieve(mixed $stripe_payment_intent_id);
}