<?php

namespace App\Events\Subscriptions;

/**
 * Fired after a scheduled subscription is transitioned to Active.
 *
 * Listeners:
 *   - SendSubscriptionWelcomeEmail   (sends the member their welcome email)
 *   - ProvisionSubscriptionAccess    (grants digital access / entitlements)
 *
 * Carry only the subscription ID and the instant of activation so that
 * listeners can reload fresh state and remain decoupled from the model
 * graph that was live at activation time.
 */
class SubscriptionActivated
{
    public function __construct(
        public readonly int                $subscriptionId,
        public readonly \DateTimeImmutable $activatedAt,
    )
    {
    }
}