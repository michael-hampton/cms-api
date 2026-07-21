<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Support\Logger;
use App\Services\Subscriptions\SubscriptionFulfilmentPauseService;

/**
 * Replaces a subscription's PAUSED fulfilments with fresh rows from the
 * next available plan issue when the subscription resumes.
 *
 * Triggered by: SubscriptionResumed
 *
 * Failure contract:
 *   The subscription is already marked active before this event is
 *   dispatched. A failure here must not undo that — catch and log.
 */
class OnSubscriptionResumedResumeFulfilments
{
    public function __construct(
        private readonly SubscriptionFulfilmentPauseService $pauseService,
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionResumed $event): void
    {
        try {
            $this->pauseService->resume($event->subscription);
        } catch (\Throwable $e) {
            $this->logger->error('OnSubscriptionResumedResumeFulfilments: failed to resume fulfilments', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
