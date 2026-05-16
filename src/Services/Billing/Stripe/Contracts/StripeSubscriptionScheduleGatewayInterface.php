<?php

namespace App\Services\Billing\Stripe\Contracts;

use App\DTO\Stripe\CreateStripeSubscriptionScheduleDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;

interface StripeSubscriptionScheduleGatewayInterface
{
    public function create(CreateStripeSubscriptionScheduleDto $dto): StripeSubscriptionResultDto;
}