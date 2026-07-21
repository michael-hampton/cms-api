<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Framework\Support\Logger;
use App\Services\Subscriptions\SubscriptionFulfilmentPauseService;

/**
 * Pauses a subscription's pending fulfilments when the subscription itself
 * is paused (SubscriptionPauseService) — a subscription-level pause,
 * distinct from the dated print-delivery pause already handled by
 * SubscriptionDeliveryService.
 *
 * Triggered by: SubscriptionPaused
 *
 * Failure contract:
 *   The subscription is already marked paused before this event is
 *   dispatched. A failure here must not undo that — catch and log.
 */
class OnSubscriptionPausedPauseFulfilments
{
    public function __construct(
        private readonly SubscriptionFulfilmentPauseService $pauseService,
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionPaused $event): void
    {
        try {
            $this->pauseService->pause($event->subscription);
        } catch (\Throwable $e) {
            $this->logger->error('OnSubscriptionPausedPauseFulfilments: failed to pause fulfilments', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
