<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Repositories\Subscriptions\SubscriptionWindowRepository;

class IssueDeliveryEligibilityService
{
    public function __construct(
        private readonly SubscriptionRepository       $subscriptionRepository,
        private readonly SubscriptionWindowRepository $subscriptionWindowRepository,
    )
    {
    }

    public function getEligibleSubscriptions(IssueDelivery $issueDelivery): Collection
    {
        $scheduledDate = $issueDelivery->on_sale_date ?? $issueDelivery->estimated_delivery_date;

        if (!$scheduledDate) {
            return new Collection([]);
        }

        $candidates = $this->subscriptionRepository->findActiveByPlanAndDate(
            $issueDelivery->subscription_plan_id,
            $scheduledDate
        );

        return $candidates;

        return $candidates->filter(
            fn(Subscription $subscription) => $this->hasActiveWindowForDate($subscription, $scheduledDate)
        );
    }

    public function isSubscriptionEligible(Subscription $subscription, IssueDelivery $issueDelivery): bool
    {
        $scheduledDate = $issueDelivery->on_sale_date ?? $issueDelivery->estimated_delivery_date;

        if (!$scheduledDate) {
            return false;
        }

        if ($subscription->plan_id !== $issueDelivery->subscription_plan_id) {
            return false;
        }

        if ($subscription->status !== SubscriptionStatus::ACTIVE->value) {
            return false;
        }

        if ($subscription->start_date && $subscription->start_date > $scheduledDate) {
            return false;
        }

        if ($subscription->end_date && $subscription->end_date < $scheduledDate) {
            return false;
        }

        return $this->hasActiveWindowForDate($subscription, $scheduledDate);
    }

    private function hasActiveWindowForDate(Subscription $subscription, \DateTime $date): bool
    {
        return $this->subscriptionWindowRepository->hasActiveWindowForDate($subscription->id, $date);
    }
}