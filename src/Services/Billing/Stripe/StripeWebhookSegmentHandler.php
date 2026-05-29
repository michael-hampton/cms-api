<?php

namespace App\Services\Billing\Stripe;

use App\Models\Subscription;
use App\Services\MemberInsights\Segmentation\SegmentAssignmentService;

/**
 * Extends Stripe webhook processing with real-time segment evaluation.
 *
 * This handler is called by the existing Stripe webhook service AFTER
 * a subscription has been upserted from the webhook payload. It delegates
 * entirely to SegmentAssignmentService — the same path used by batch
 * recalculation (Ticket 8).
 *
 * No assignment logic lives here. This class is purely a coordinator.
 *
 * Integration:
 *   Inject this into your existing StripeWebhookService (or equivalent)
 *   and call evaluateSubscription() after each subscription upsert.
 */
class StripeWebhookSegmentHandler
{
    public function __construct(
        private readonly SegmentAssignmentService $assignmentService,
    ) {
    }

    /**
     * Evaluate and assign segment after subscription_created.
     */
    public function onSubscriptionCreated(Subscription $subscription): void
    {
        $this->assignmentService->assignForSubscription($subscription);
    }

    /**
     * Evaluate and assign segment after subscription_updated.
     */
    public function onSubscriptionUpdated(Subscription $subscription): void
    {
        $this->assignmentService->assignForSubscription($subscription);
    }

    /**
     * Evaluate and assign segment after subscription_renewed.
     */
    public function onSubscriptionRenewed(Subscription $subscription): void
    {
        $this->assignmentService->assignForSubscription($subscription);
    }

    /**
     * Evaluate and assign segment after subscription_cancelled.
     * Even cancelled subscriptions may match a 'cancelled' segment for
     * win-back targeting.
     */
    public function onSubscriptionCancelled(Subscription $subscription): void
    {
        $this->assignmentService->assignForSubscription($subscription);
    }
}
