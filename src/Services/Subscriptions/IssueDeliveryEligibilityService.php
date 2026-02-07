<?php

namespace App\Services\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\SubscriptionWindow;

class IssueDeliveryEligibilityService
{
    public function getEligibleSubscriptions(IssueDelivery $issueDelivery): Collection
    {
        $scheduledDate = $issueDelivery->on_sale_date ?? $issueDelivery->estimated_delivery_date;

        if (!$scheduledDate) {
            return new Collection([]);
        }

        // Get subscriptions matching the plan
        $subscriptions = Subscription::where('plan_id', $issueDelivery->subscription_plan_id)
            ->where('status', 'active')
            ->where(function ($query) use ($scheduledDate) {
                $query->where('start_date', '<=', $scheduledDate->format('Y-m-d H:i:s'))
                    ->orWhereNull('start_date');
            })
            ->where(function ($query) use ($scheduledDate) {
                $query->where('end_date', '>=', $scheduledDate->format('Y-m-d H:i:s'))
                    ->orWhereNull('end_date');
            })
            ->get();

        // Filter by subscription windows
        return $subscriptions->filter(function ($subscription) use ($scheduledDate) {
            return $this->hasActiveWindowForDate($subscription, $scheduledDate);
        });
    }

    private function hasActiveWindowForDate(Subscription $subscription, \DateTime $date): bool
    {
        return SubscriptionWindow::where('subscription_id', $subscription->id)
            ->where('window_start', '<=', $date->format('Y-m-d H:i:s'))
            ->where(function ($query) use ($date) {
                $query->where('window_end', '>=', $date->format('Y-m-d H:i:s'))
                    ->orWhereNull('window_end');
            })
            ->exists();
    }

    public function isSubscriptionEligible(Subscription $subscription, IssueDelivery $issueDelivery): bool
    {
        $scheduledDate = $issueDelivery->on_sale_date ?? $issueDelivery->estimated_delivery_date;

        if (!$scheduledDate) {
            return false;
        }

        // Must match plan
        if ($subscription->plan_id !== $issueDelivery->subscription_plan_id) {
            return false;
        }

        // Must be active
        if ($subscription->status !== 'active') {
            return false;
        }

        // Must have started
        if ($subscription->start_date && $subscription->start_date > $scheduledDate) {
            return false;
        }

        // Must not have ended
        if ($subscription->end_date && $subscription->end_date < $scheduledDate) {
            return false;
        }

        // Must have active window
        return $this->hasActiveWindowForDate($subscription, $scheduledDate);
    }
}