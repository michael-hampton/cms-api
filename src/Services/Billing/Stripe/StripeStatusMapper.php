<?php

namespace App\Services\Billing\Stripe;

/**
 * Maps Stripe subscription/invoice statuses to internal values.
 *
 * Centralising the mapping here means action classes never contain raw
 * string literals and the mapping is easy to extend.
 */
class StripeStatusMapper
{
    /**
     * Map a Stripe subscription status to our local SubscriptionStatus value.
     *
     * Stripe statuses: active | trialing | past_due | canceled | unpaid |
     *                  incomplete | incomplete_expired | paused
     */
    public static function subscriptionStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing'           => 'active',
            'past_due'                     => 'past_due',
            'canceled', 'incomplete_expired' => 'cancelled',
            'unpaid'                       => 'unpaid',
            default                        => 'canceled',
        };
    }
}