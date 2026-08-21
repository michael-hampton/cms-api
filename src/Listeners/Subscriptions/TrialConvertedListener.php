<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\TrialConvertedEvent;
use App\Framework\Support\Logger;

/**
 * Audit trail for successful trial-to-paid conversions.
 *
 * The event's docblock calls for a "welcome to paid" email and gated
 * content provisioning — same caveat as SubscriptionActivatedListener:
 * no communication key is seeded for this yet, so building that flow here
 * would mean inventing product content rather than following an
 * established pattern. This listener performs the one job it can do
 * without inventing that decision: a real, auditable log entry.
 */
class TrialConvertedListener
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function handle(TrialConvertedEvent $event): void
    {
        $this->logger->info('TrialConvertedListener: trial converted to paid subscription', [
            'subscription_id' => $event->subscription->id,
            'member_id' => $event->subscription->member_id,
        ]);
    }
}
