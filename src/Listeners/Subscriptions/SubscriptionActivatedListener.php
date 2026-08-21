<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionActivated;
use App\Framework\Support\Logger;

/**
 * Audit trail for scheduled-to-active subscription transitions.
 *
 * The event's docblock calls out a welcome email and digital-access
 * provisioning as the intended listeners (SendSubscriptionWelcomeEmail /
 * ProvisionSubscriptionAccess). Neither exists yet, and there's no
 * communication key seeded for "activated" the way there is for
 * acknowledgement/renewal/etc — building that email/entitlement flow
 * without a decision on the actual template and access rules would mean
 * guessing at product content rather than following an established
 * pattern. This listener performs the one job it can do without inventing
 * that decision: a real, auditable log entry, matching the existing
 * logging-listener pattern used elsewhere in this namespace.
 */
class SubscriptionActivatedListener
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionActivated $event): void
    {
        $this->logger->info('SubscriptionActivatedListener: subscription activated', [
            'subscription_id' => $event->subscriptionId,
            'activated_at' => $event->activatedAt->format('Y-m-d H:i:s'),
        ]);
    }
}
