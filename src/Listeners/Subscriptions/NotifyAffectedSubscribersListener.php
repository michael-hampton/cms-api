<?php

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionPricingChangeScheduled;
use App\Jobs\Subscriptions\SendPricingChangeNoticeJob;
use App\Jobs\SyncMemberToStripeJob;
use App\Repositories\Subscriptions\SubscriptionPricingChangeRepository;

/**
 * Listener for SubscriptionPricingChangeScheduled.
 *
 * Responsibilities:
 *  1. Load all active subscribers on the affected plan.
 *  2. Dispatch a SendPricingChangeNoticeJob per subscriber (queued, non-blocking).
 *  3. Mark the pricing change as 'notified' once all jobs are dispatched.
 *
 * Performing work #3 here (rather than waiting for all individual jobs to
 * complete) is intentional: "notified" means "notice process initiated", not
 * "every email confirmed delivered". The status exists primarily to prevent
 * duplicate notification runs, not to guarantee 100% delivery.
 */
class NotifyAffectedSubscribersListener
{
    public function __construct(
        private readonly SubscriptionPricingChangeRepository $repository,
    )
    {
    }

    public function handle(SubscriptionPricingChangeScheduled $event): void
    {
        $pricingChange = $event->pricingChange;
        $subscriptions = $this->repository->findActiveSubscribersForPlan($pricingChange->plan_id);

        foreach ($subscriptions as $subscription) {
            $member = $subscription->member(true)->first();

            if (!$member) {
                continue;
            }

            dispatch(SendPricingChangeNoticeJob::for(
                $member,
                $subscription,
                $pricingChange,
            ))->dispatchNow();
        }

        $this->repository->markNotified($pricingChange);
    }
}