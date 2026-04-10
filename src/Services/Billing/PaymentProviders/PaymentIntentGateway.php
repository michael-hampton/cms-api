<?php

namespace App\Services\Billing\PaymentProviders;

interface PaymentIntentGateway
{
    public function create(array $data);
}