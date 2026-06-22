<?php

namespace App\Services\Billing\Stripe\Contracts;

use App\DTO\Stripe\CreateStripeSubscriptionDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;
use DateTimeImmutable;

interface StripeSubscriptionGatewayInterface
{
    public function create(CreateStripeSubscriptionDto $dto): StripeSubscriptionResultDto;

    public function createWithTrial(CreateStripeSubscriptionDto $dto): StripeSubscriptionResultDto;

    public function pauseCollection(string $stripeSubscriptionId): void;

    public function resumeCollection(string $stripeSubscriptionId): ?DateTimeImmutable;

    public function moveEndDate(string $stripeSubscriptionId, DateTimeImmutable $newEndDate): void;
}
