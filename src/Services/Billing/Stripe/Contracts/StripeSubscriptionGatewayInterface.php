<?php

namespace App\Services\Billing\Stripe\Contracts;

use App\DTO\Stripe\CreateStripeSubscriptionDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;

interface StripeSubscriptionGatewayInterface
{
    public function create(CreateStripeSubscriptionDto $dto): StripeSubscriptionResultDto;

    public function createWithTrial(CreateStripeSubscriptionDto $dto): StripeSubscriptionResultDto;
}