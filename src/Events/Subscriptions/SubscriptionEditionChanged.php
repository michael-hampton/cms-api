<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

class SubscriptionEditionChanged
{
    public function __construct(
        public readonly int    $subscriptionId,
        public readonly int    $oldEditionId,
        public readonly int    $newEditionId,
        public readonly int    $agentId,
        public readonly string $timestamp,
    ) {}
}