<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionSuspended;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentSuspensionService;

/**
 * Suspends the subscription's pending fulfilments when an admin/system
 * suspension occurs, using the exact same mechanism and business rule as a
 * payment failure (FulfilmentSuspensionService).
 *
 * Triggered by: SubscriptionSuspended
 *
 * Failure contract:
 *   The subscription is already marked suspended and access already
 *   revoked before this event is dispatched (SuspendSubscriptionAction).
 *   A failure here must not undo that — catch and log.
 */
class OnSubscriptionSuspendedSuspendFulfilments
{
    public function __construct(
        private readonly FulfilmentSuspensionService $fulfilmentSuspensionService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionSuspended $event): void
    {
        try {
            $subscription = $this->subscriptionRepository->find($event->subscriptionId);

            if (!$subscription) {
                $this->logger->warning('OnSubscriptionSuspendedSuspendFulfilments: subscription not found', [
                    'subscription_id' => $event->subscriptionId,
                ]);

                return;
            }

            $this->fulfilmentSuspensionService->handleTrigger(
                $subscription,
                FulfilmentSuspensionService::REASON_SUBSCRIPTION_SUSPENDED,
            );
        } catch (\Throwable $e) {
            $this->logger->error('OnSubscriptionSuspendedSuspendFulfilments: failed to suspend fulfilments', [
                'subscription_id' => $event->subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
