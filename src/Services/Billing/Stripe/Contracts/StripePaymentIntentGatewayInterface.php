<?php

namespace App\Services\Billing\Stripe\Contracts;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\DTO\Stripe\PaymentIntentResultDto;

interface StripePaymentIntentGatewayInterface
{
    public function create(CreatePaymentIntentDto $dto): PaymentIntentResultDto;

    public function createWithCustomer(CreatePaymentIntentDto $dto): PaymentIntentResultDto;

    public function retrieve(string $paymentIntentId): PaymentIntentResultDto;
    public function confirmPaymentIntent(string $paymentIntentId): array;
}