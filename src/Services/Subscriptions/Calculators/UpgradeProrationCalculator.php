<?php

namespace App\Services\Subscriptions\Calculators;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\ValueObjects\UpgradeQuote;
use App\Services\ValueObjects\Money;
use InvalidArgumentException;

class UpgradeProrationCalculator
{
    public function calculateUpgradeQuote(
        Subscription     $subscription,
        SubscriptionPlan $upgradePlan
    ): UpgradeQuote
    {
        $currentPrice = Money::fromDecimal($subscription->price, $subscription->currency);
        $upgradePrice = Money::fromDecimal($upgradePlan->price, $upgradePlan->currency ?? $subscription->currency);

        // Ensure currency match
        if ($currentPrice->getCurrency() !== $upgradePrice->getCurrency()) {
            throw new \InvalidArgumentException(
                "Currency mismatch: subscription is {$currentPrice->getCurrency()}, upgrade plan is {$upgradePrice->getCurrency()}"
            );
        }

        $priceDifference = $upgradePrice->subtract($currentPrice);

        $remainingDays = null;
        $isProrated = false;

        // Calculate proration for recurring Stripe subscriptions
        if ($subscription->hasStripeSubscription() && $subscription->next_billing_date) {
            $now = new \DateTimeImmutable();
            $nextBilling = \DateTimeImmutable::createFromMutable($subscription->next_billing_date);
            $startDate = \DateTimeImmutable::createFromMutable($subscription->start_date);

            // Guard: billing date before today
            if ($nextBilling->format('Y-m-d') < $now->format('Y-m-d')) {
                throw new InvalidArgumentException('Next billing date is in the past');
            }

            if ($startDate > $nextBilling) {
                throw new \InvalidArgumentException('Start date cannot be after next billing date');
            }

            $totalDays = $startDate->diff($nextBilling)->days;
            $remainingDays = $now->diff($nextBilling)->days;

            // Billing day → no remaining time
            if ($remainingDays === 0) {
                return new UpgradeQuote(
                    Money::fromCents(0, $subscription->currency),
                    false,
                    0,
                    true
                );
            }

            if ($totalDays > 0) {
                $prorationFactor = $remainingDays / $totalDays;
                $priceDifference = $priceDifference->multiply($prorationFactor);
                $isProrated = true;
            }
        }

        $finalAmount = $priceDifference->isPositive()
            ? $priceDifference
            : Money::fromCents(0, $subscription->currency);

        // Mark as estimate for Stripe subscriptions
        $isEstimate = $subscription->hasStripeSubscription();

        return new UpgradeQuote($finalAmount, $isProrated, $remainingDays, $isEstimate);
    }
}