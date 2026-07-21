<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionCancelledByStripe;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentCancellationService;

/**
 * Cancels a subscription's remaining pending fulfilments whenever the
 * subscription itself is cancelled — whether member/admin-initiated
 * (SubscriptionCancelled) or by Stripe (SubscriptionCancelledByStripe).
 *
 * Failure contract:
 *   The subscription is already marked cancelled before either event is
 *   dispatched. A failure here must not undo that — catch and log.
 */
class OnSubscriptionCancelledCancelFulfilments
{
    public function __construct(
        private readonly FulfilmentCancellationService $fulfilmentCancellationService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionCancelled $event): void
    {
        try {
            $subscription = $this->subscriptionRepository->find($event->subscriptionId);

            if (!$subscription) {
                $this->logger->warning('OnSubscriptionCancelledCancelFulfilments: subscription not found', [
                    'subscription_id' => $event->subscriptionId,
                ]);

                return;
            }

            $this->fulfilmentCancellationService->cancel($subscription);
        } catch (\Throwable $e) {
            $this->logger->error('OnSubscriptionCancelledCancelFulfilments: failed to cancel fulfilments', [
                'subscription_id' => $event->subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleCancelledByStripe(SubscriptionCancelledByStripe $event): void
    {
        try {
            $this->fulfilmentCancellationService->cancel($event->subscription);
        } catch (\Throwable $e) {
            $this->logger->error('OnSubscriptionCancelledCancelFulfilments: failed to cancel fulfilments (Stripe)', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
