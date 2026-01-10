<?php

namespace App\Services\Subscriptions;

use App\Models\Member;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionRepository;

class SubscriptionModalService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionRepository     $subscriptionRepository
    )
    {
    }

    public function getModalData(?Member $member, int $siteId): array
    {
        $plans = $this->planRepository->getActivePlans($siteId);

        // Limit to 3 plans for the modal
        $plans = $plans->take(3);

        return [
            'show_modal' => $this->shouldShowModal($member, $siteId),
            'plans' => $plans,
            'member' => $member
        ];
    }

    public function shouldShowModal(?Member $member, int $siteId): bool
    {
        if (!$member) {
            return true;
        }

        // Check if member should see the modal
        if (!$this->subscriptionRepository->shouldShowSubscriptionModal($member->id, $siteId)) {
            return false;
        }

        // Check if there are any active plans to show
        $plans = $this->planRepository->getActivePlans($siteId);
        return $plans->count() > 0;
    }

    public function markModalShown(int $memberId, int $siteId): void
    {
        $this->subscriptionRepository->setMemberSubscriptionCheckTime($memberId, $siteId);
    }
}