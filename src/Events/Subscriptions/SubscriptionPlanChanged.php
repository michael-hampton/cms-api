<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

class SubscriptionPlanChanged
{
    public function __construct(
        public readonly int $subscriptionId,
        public readonly int $oldPlanId,
        public readonly int $newPlanId,
        public readonly int $agentId,
        public readonly string $timestamp,
    ) {}
}