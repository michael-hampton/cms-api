<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

class SubscriptionRepository extends Repository
{
    protected function getModelClass(): string
    {
        return Subscription::class;
    }

    public function getActiveSubscriptionForMember(int $memberId, ?int $siteId = null): ?Subscription
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->first();
    }

    public function getSubscriptionHistory(int $memberId, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function countActiveSubscriptions(int $memberId, ?int $siteId = null): int
    {
        $siteId = $siteId ?? $this->siteId;

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->count();
    }

    public function cancelSubscription(int $subscriptionId): bool
    {
        $subscription = $this->find($subscriptionId);

        if (!$subscription) {
            return false;
        }

        return $this->update($subscriptionId, [
                'status' => 'cancelled',
                'auto_renew' => false
            ]) !== null;
    }

    public function createSubscription(int $memberId, int $planId, int $siteId, array $additionalData = []): Model
    {
        $plan = SubscriptionPlan::find($planId);

        $startDate = new \DateTime();
        $endDate = null;
        $nextBillingDate = null;

        if ($plan->billing_period !== 'lifetime') {
            $endDate = clone $startDate;
            $nextBillingDate = clone $startDate;

            match ($plan->billing_period) {
                'monthly' => $endDate->modify('+1 month'),
                'quarterly' => $endDate->modify('+3 months'),
                'yearly' => $endDate->modify('+1 year'),
            };

            // Set next billing date same as end date initially
            match ($plan->billing_period) {
                'monthly' => $nextBillingDate->modify('+1 month'),
                'quarterly' => $nextBillingDate->modify('+3 months'),
                'yearly' => $nextBillingDate->modify('+1 year'),
            };
        }

        return Subscription::create(array_merge([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'plan_id' => $planId,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate?->format('Y-m-d H:i:s'),
            'next_billing_date' => $nextBillingDate?->format('Y-m-d H:i:s'),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'auto_renew' => $plan->billing_period !== 'lifetime'
        ], $additionalData));
    }

    public function hasActiveSubscriptionToPlan(int $memberId, int $planId, ?int $siteId = null): bool
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('member_id', $memberId)
            ->where('plan_id', $planId)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->exists();
    }

    public function getActiveSubscriptionForPlan(int $memberId, int $planId, ?int $siteId = null): ?Subscription
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('member_id', $memberId)
            ->where('plan_id', $planId)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->first();
    }

    public function getMemberLastSubscriptionCheck(int $memberId, int $siteId): ?string
    {
        // Check session/cookie for last time we showed the modal
        $key = "subscription_modal_shown_{$memberId}_{$siteId}";
        return $_SESSION[$key] ?? null;
    }

    public function setMemberSubscriptionCheckTime(int $memberId, int $siteId): void
    {
        $key = "subscription_modal_shown_{$memberId}_{$siteId}";
        $_SESSION[$key] = date('Y-m-d H:i:s');
    }

    public function shouldShowSubscriptionModal(int $memberId, int $siteId): bool
    {
        // Don't show if they have active subscription
        if ($this->getActiveSubscriptionForMember($memberId, $siteId)) {
            return false;
        }

        // Don't show if we showed it in the last 24 hours
        $lastShown = $this->getMemberLastSubscriptionCheck($memberId, $siteId);

//        if ($lastShown) {
//            $lastShownTime = strtotime($lastShown);
//            $hoursSince = (time() - $lastShownTime) / 3600;
//            if ($hoursSince < 24) {
//                return false;
//            }
//        }

        return true;
    }

    public function getSubscriptionsDueForRenewal(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('site_id', $siteId)
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->whereNotNull('next_billing_date')
            ->where('next_billing_date', '<=', date('Y-m-d H:i:s'))
            ->get();
    }

    public function updateNextBillingDate(int $subscriptionId, \DateTime $nextBillingDate): bool
    {
        return $this->update($subscriptionId, [
                'next_billing_date' => $nextBillingDate->format('Y-m-d H:i:s')
            ]) !== null;
    }

    public function updateLastPaymentDate(int $subscriptionId, \DateTime $lastPaymentDate): bool
    {
        return $this->update($subscriptionId, [
                'last_payment_date' => $lastPaymentDate->format('Y-m-d H:i:s')
            ]) !== null;
    }

    public function markAsPastDue(int $subscriptionId): bool
    {
        return $this->update($subscriptionId, [
                'status' => 'past_due'
            ]) !== null;
    }

    public function getSubscriptionsWithFailedPayments(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('site_id', $siteId)
            ->where('status', 'past_due')
            ->get();
    }
}