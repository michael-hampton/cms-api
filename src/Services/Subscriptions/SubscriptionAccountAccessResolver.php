<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;

final class SubscriptionAccountAccessResolver
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function resolve(int $subscriptionId, int $memberId, ?int $siteId): ?Subscription
    {
        return $this->subscriptionRepository->findForMemberAccess($subscriptionId, $memberId, $siteId);
    }
}
