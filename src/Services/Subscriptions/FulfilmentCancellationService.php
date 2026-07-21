<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;

/**
 * Cancels a subscriber's remaining pending fulfilments when their
 * subscription is cancelled — member-initiated, admin-initiated or by
 * Stripe. CANCELLED is terminal: unlike SUSPENDED/PAUSED, these rows are
 * never reactivated.
 */
class FulfilmentCancellationService
{
    public function __construct(
        private readonly SubscriptionIssueFulfilmentRepository $fulfilmentRepository,
        private readonly Logger $logger,
    ) {
    }

    public function cancel(Subscription $subscription): int
    {
        $count = $this->fulfilmentRepository->cancelPendingForSubscription((int) $subscription->id);

        $this->logger->info('FulfilmentCancellationService: pending fulfilments cancelled', [
            'subscription_id' => $subscription->id,
            'count' => $count,
        ]);

        return $count;
    }
}
