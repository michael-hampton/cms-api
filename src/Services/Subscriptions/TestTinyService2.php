<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;

class TestTinyService2
{
    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly SubscriptionIssueFulfilmentRepository $subscriptionIssueFulfilmentRepository,
    ) {
    }
}
