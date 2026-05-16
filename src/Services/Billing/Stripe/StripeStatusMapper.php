<?php

namespace App\Services\Billing\Stripe;

use App\Enums\Subscriptions\SubscriptionStatus;

/**
 * Maps Stripe subscription/invoice statuses to internal SubscriptionStatus values.
 *
 * Rules:
 * - trialing is preserved as its own status (not collapsed into active)
 * - past_due is preserved for payment retry logic
 * - Everything else maps to a safe fallback
 */
class StripeStatusMapper
{
    public static function subscriptionStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active'               => SubscriptionStatus::ACTIVE->value,
            'trialing'             => SubscriptionStatus::TRIALING->value,
            'past_due'             => SubscriptionStatus::PAST_DUE->value,
            'canceled',
            'incomplete_expired'   => SubscriptionStatus::CANCELLED->value,
            'unpaid'               => SubscriptionStatus::UNPAID->value,
            'incomplete'           => SubscriptionStatus::INCOMPLETE->value,
            default                => SubscriptionStatus::CANCELLED->value,
        };
    }
}