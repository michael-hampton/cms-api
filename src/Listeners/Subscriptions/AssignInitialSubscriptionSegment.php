<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionCreated;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\MemberInsights\Segmentation\SegmentAssignmentService;

/**
 * Evaluates and assigns the initial rule-based subscription segment (e.g.
 * free_subscription / paid_subscription) whenever a subscription is created
 * outside the Stripe webhook path — the direct repository path and the
 * shared checkout path both dispatch SubscriptionCreated, which covers
 * storefront checkout, admin/CRM-created subscriptions, and one-time
 * subscriptions.
 *
 * The Stripe webhook path is wired separately via StripeWebhookSegmentHandler
 * (invoked directly from HandleSubscriptionCreated / HandleSubscriptionUpdated)
 * since those subscriptions aren't created through this event.
 *
 * Failure contract:
 *   Segmentation is a non-critical, cross-cutting side effect. It must never
 *   fail subscription creation — catch and log.
 */
class AssignInitialSubscriptionSegment
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SegmentAssignmentService $segmentAssignmentService,
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionCreated $event): void
    {
        try {
            $subscription = $this->subscriptionRepository->find($event->subscriptionId);

            if ($subscription === null) {
                return;
            }

            $this->segmentAssignmentService->assignForSubscription($subscription);
        } catch (\Throwable $e) {
            $this->logger->error('AssignInitialSubscriptionSegment: segment evaluation failed', [
                'subscription_id' => $event->subscriptionId,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}