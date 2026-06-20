<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

interface SubscriptionAccountEndpointProviderInterface
{
    public function for(Subscription $subscription): array;
}
