<?php

namespace App\Services\Subscriptions;

interface SubscriptionAccountEndpointProviderInterface
{
    public function forId(int $subscriptionId): array;
}
