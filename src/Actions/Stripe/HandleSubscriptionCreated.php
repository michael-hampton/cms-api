<?php

namespace App\Actions\Stripe;

use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\StripeStatusMapper;
use App\Services\Billing\Stripe\StripeWebhookSegmentHandler;
use App\Framework\Support\Logger;
use Stripe\Event;

/**
 * Handles customer.subscription.created
 *
 * Creates or updates a local Subscription record from the Stripe payload.
 * Uses updateOrCreate so re-delivery of this event is safe.
 *
 * member_id / site_id / plan_id are resolved from Stripe metadata, which is
 * populated by StripePaymentProcessor::createStripeSubscription.
 *
 * plan_name (NOT NULL) is resolved by:
 *   1. SubscriptionPlanRepository::find() — canonical local source.
 *   2. Stripe product name on the first subscription item — fallback for the
 *      edge case where no local plan record exists yet.
 *   3. Hard fallback string — prevents a NOT NULL constraint failure on a
 *      malformed payload.
 */
class HandleSubscriptionCreated
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly StripeWebhookSegmentHandler $segmentHandler,
        private readonly Logger $logger,
    ) {}

    public function handle(Event $event): void
    {
        /** @var \Stripe\Subscription $stripeSub */
        $stripeSub = $event->data->object;

        $metadata = $stripeSub->metadata ?? [];
        $memberId = $metadata['member_id'] ?? null;
        $siteId   = $metadata['site_id']   ?? null;
        $planId   = $metadata['plan_id']   ?? null;

        $planName = $this->resolvePlanName($planId ? (int) $planId : null, $stripeSub);

        $subscription = Subscription::updateOrCreate(
            ['payment_subscription_id' => $stripeSub->id],
            [
                'member_id'               => $memberId,
                'site_id'                 => $siteId,
                'plan_id'                 => $planId,
                'plan_name'               => $planName,
                'stripe_customer_id'      => $stripeSub->customer,
                'payment_subscription_id' => $stripeSub->id,
                'stripe_schedule_id' => $stripeSub->schedule
                    ? (is_string($stripeSub->schedule) ? $stripeSub->schedule : $stripeSub->schedule->id)
                    : null,
                'status'                  => StripeStatusMapper::subscriptionStatus($stripeSub->status),
                'current_period_start'    => $stripeSub->current_period_start
                    ? date('Y-m-d H:i:s', $stripeSub->current_period_start)
                    : null,
                'current_period_end'      => $stripeSub->current_period_end
                    ? date('Y-m-d H:i:s', $stripeSub->current_period_end)
                    : null,
                'cancel_at_period_end'    => (bool) $stripeSub->cancel_at_period_end,
                'start_date'              => $stripeSub->start_date
                    ? date('Y-m-d H:i:s', $stripeSub->start_date)
                    : date('Y-m-d H:i:s', $stripeSub->created),
                'type'                    => 'paid',
                'auto_renew'              => true,
            ]
        );

        // Segment assignment is a non-critical, cross-cutting side effect —
        // never let it fail webhook processing (which Stripe would retry).
        try {
            $this->segmentHandler->onSubscriptionCreated($subscription);
        } catch (\Throwable $e) {
            $this->logger->error('HandleSubscriptionCreated: segment evaluation failed', [
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    private function resolvePlanName(?int $planId, \Stripe\Subscription $stripeSub): string
    {
        if ($planId !== null) {
            $plan = $this->planRepository->find($planId);

            if ($plan !== null) {
                return $plan->name;
            }
        }

        $firstItem   = $stripeSub->items->data[0] ?? null;
        $productName = $firstItem?->price?->product?->name ?? null;

        return $productName ?? 'Unknown Plan';
    }
}