<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;

class RenewalIssueSchedulingService
{
    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly SubscriptionIssueFulfilmentRepository $subscriptionIssueFulfilmentRepository,
    ) {
    }

    public function replaceFutureFulfilmentsForRenewal(Subscription $oldSubscription, Subscription $newSubscription): array
    {
        return ['old_superseded' => 0, 'new_created' => 0, 'new_existing' => 0, 'new_skipped' => 0];
    }
}
