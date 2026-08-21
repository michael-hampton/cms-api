<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\TrialConversionFailedEvent;
use App\Framework\Support\Logger;

/**
 * Audit trail for declined trial-conversion payments.
 *
 * The event's docblock calls for notifying the member with a reactivation
 * link — that needs a decision on the actual communication template/channel,
 * and there's no "trial_conversion_failed" key seeded the way there is for
 * payment_failed_letter_default etc, so building that notification here
 * would mean inventing product content rather than following an
 * established pattern. This listener performs the one job it can do
 * without inventing that decision: a real, auditable log entry, so failed
 * conversions are visible in the log stream even before the member
 * notification flow is built.
 */
class TrialConversionFailedListener
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function handle(TrialConversionFailedEvent $event): void
    {
        $this->logger->warning('TrialConversionFailedListener: trial conversion payment declined', [
            'subscription_id' => $event->subscription->id,
            'member_id' => $event->subscription->member_id,
            'reason' => $event->reason,
        ]);
    }
}
