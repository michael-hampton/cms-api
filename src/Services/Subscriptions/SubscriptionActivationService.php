<?php

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionActivated;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Activates subscriptions whose start_date has passed and whose status is
 * still Scheduled.
 *
 * Each subscription is activated individually so that a failure on one
 * record does not block the rest of the batch. Failures are logged and
 * skipped — activation is non-critical in the sense that the job will
 * re-attempt the same record on the next run (the status will still be
 * Scheduled), so silent retry is acceptable here.
 *
 * A SubscriptionActivated event is emitted per subscription so that
 * downstream concerns (welcome emails, access provisioning, analytics)
 * can react without coupling to this service.
 */
class SubscriptionActivationService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Logger                 $logger,
    )
    {
    }

    /**
     * Activate all scheduled subscriptions that are due as of $asOf.
     *
     * @return array{activated: int, failed: int}
     */
    public function activateScheduled(\DateTimeImmutable $asOf): array
    {
        $subscriptions = $this->subscriptionRepository->getScheduledDue($asOf);

        $activated = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $this->subscriptionRepository->markAsActive($subscription, $asOf);

                event(new SubscriptionActivated($subscription->id, $asOf));

                $activated++;
            } catch (\Throwable $e) {
                $failed++;

                $this->logger->error('Failed to activate scheduled subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return compact('activated', 'failed');
    }
}