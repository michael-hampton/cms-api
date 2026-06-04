<?php

namespace App\Services\Billing\Preorder;

use App\Models\SubscriptionPlan;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;

class SubscriptionAvailabilityPolicy implements AvailabilityPolicyInterface
{
    public function __construct(
        private readonly SubscriptionPlan $plan
    )
    {
    }

    public function canPurchase(): bool
    {
        // Plan not released
        if (!$this->isPlanReleased()) {
            if (!$this->plan->pre_release_enabled) {
                return false;
            }

            if (!$this->plan->hasPrintOption()) {
                return true;
            }

            $nextIssue = $this->plan->getNextIssue();
            return $nextIssue?->availabilityPolicy()->canPurchase() ?? false;
        }

        // Digital
        if (!$this->plan->hasPrintOption()) {
            return true;
        }

        // Print (released)
        $nextIssue = $this->plan->getNextIssue();

        return $nextIssue?->availabilityPolicy()->canPurchase() ?? false;
    }

    /**
     * Check if the plan itself has been released
     */
    private function isPlanReleased(): bool
    {
        if (!$this->plan->release_date) {
            return true;
        }

        return $this->plan->release_date <= now_datetime();
    }

    public function isPreOrder(): bool
    {
        if (!$this->plan->hasPrintOption()) {
            return false;
        }

        $nextIssue = $this->plan->getNextIssue();

        if (!$nextIssue) {
            return false;
        }

        return $nextIssue->availabilityPolicy()->isPreOrder();
    }

    public function isPreRelease(): bool
    {
        // Check if PLAN itself is pre-release (not yet released)
        $planIsPreRelease = $this->plan->release_date
            && $this->plan->release_date > now_datetime()
            && $this->plan->pre_release_enabled;

        if (!$this->plan->hasPrintOption()) {
            // Digital: only check plan release
            return $planIsPreRelease;
        }

        // Print: check BOTH plan AND next issue
        if ($planIsPreRelease) {
            return true;
        }

        $nextIssue = $this->plan->getNextIssue();

        if (!$nextIssue) {
            return false;
        }

        return $nextIssue->availabilityPolicy()->isPreRelease();
    }

    public function getAvailabilityMessage(): string
    {
        // Check plan release first
        if (!$this->isPlanReleased()) {
            if ($this->plan->pre_release_enabled) {
                $date = $this->plan->release_date->format('M j, Y');
                return "Available for Pre-order (Launches {$date})";
            }

            $date = $this->plan->release_date->format('M j, Y');
            return "Available from {$date}";
        }

        if (!$this->plan->hasPrintOption()) {
            // Digital subscription - plan is released
            return 'Available Now';
        }

        // Print subscription - check next issue
        $nextIssue = $this->plan->getNextIssue();

        if (!$nextIssue) {
            return 'No issues available';
        }

        return $nextIssue->availabilityPolicy()->getAvailabilityMessage();
    }

    public function getExpectedShipDate(): ?\DateTime
    {
        if (!$this->plan->hasPrintOption()) {
            return null;
        }

        // If plan isn't released yet, return plan release date
        if (!$this->isPlanReleased()) {
            return $this->plan->release_date;
        }

        // Otherwise check next issue
        $nextIssue = $this->plan->getNextIssue();

        if (!$nextIssue) {
            return null;
        }

        return $nextIssue->availabilityPolicy()->getExpectedShipDate();
    }
}
