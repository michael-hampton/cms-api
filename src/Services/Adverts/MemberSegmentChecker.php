<?php

namespace App\Services\Adverts;

use App\Models\Member;

class MemberSegmentChecker
{
    public function isInSegment(Member $member, string $segment): bool
    {
        return $this->getCurrentSegment($member) === $segment;
    }

    public function getCurrentSegment(Member $member): string
    {
        // Get active subscription - this is a method call, not a relationship
        $subscription = $member->activeSubscription(false, $member->site_id);

        if (!$subscription || !$subscription->plan) {
            return 'free';
        }

        // Derive segment from plan billing period
        $billingPeriod = $subscription->plan->billing_period;
        $planType = $subscription->plan->plan_type;

        if ($planType === 'onetime') {
            return 'onetime';
        }

        if ($planType === 'recurring') {
            return match ($billingPeriod) {
                'yearly' => 'premium',
                'quarterly' => 'standard',
                'monthly' => 'basic',
                default => 'paid'
            };
        }

        return 'free';
    }

    public function isInAnySegment(Member $member, array $segments): bool
    {
        $currentSegment = $this->getCurrentSegment($member);
        return in_array($currentSegment, $segments, true);
    }
}