<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionUnsuspended;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentSuspensionService;

/**
 * Releases a subscription's suspended fulfilments when an admin/agent lifts
 * an enforcement suspension — the counterpart to
 * OnSubscriptionSuspendedSuspendFulfilments.
 *
 * Triggered by: SubscriptionUnsuspended
 *
 * Failure contract:
 *   The subscription is already restored to active before this event is
 *   dispatched. A failure here must not undo that — catch and log.
 */
class OnSubscriptionUnsuspendedReleaseFulfilments
{
    public function __construct(
        private readonly FulfilmentSuspensionService $fulfilmentSuspensionService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionUnsuspended $event): void
    {
        try {
            $subscription = $this->subscriptionRepository->find($event->subscriptionId);

            if (!$subscription) {
                $this->logger->warning('OnSubscriptionUnsuspendedReleaseFulfilments: subscription not found', [
                    'subscription_id' => $event->subscriptionId,
                ]);

                return;
            }

            $this->fulfilmentSuspensionService->release($subscription);
        } catch (\Throwable $e) {
            $this->logger->error('OnSubscriptionUnsuspendedReleaseFulfilments: failed to release fulfilments', [
                'subscription_id' => $event->subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
