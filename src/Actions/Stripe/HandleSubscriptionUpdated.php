<?php

namespace App\Actions\Stripe;

use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeStatusMapper;
use App\Services\Billing\Stripe\StripeWebhookSegmentHandler;
use Stripe\Event;

class HandleSubscriptionUpdated
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly StripeWebhookSegmentHandler $segmentHandler,
    ) {}

    public function handle(Event $event): void
    {
        $stripeSub = $event->data->object;

        $subscription = $this->subscriptionRepository
            ->findSubscriptionByStripeId($stripeSub->id);

        if (!$subscription) {
            Logger::warning('HandleSubscriptionUpdated: subscription not found locally', [
                'payment_subscription_id' => $stripeSub->id,
            ]);
            return;
        }

        $changes = [
            'status' => StripeStatusMapper::subscriptionStatus($stripeSub->status),
        ];

        if ($stripeSub->current_period_start) {
            $changes['current_period_start'] = date('Y-m-d H:i:s', $stripeSub->current_period_start);
        }

        if ($stripeSub->current_period_end) {
            $changes['current_period_end'] = date('Y-m-d H:i:s', $stripeSub->current_period_end);
        }

        $isScheduleManaged = !empty($stripeSub->schedule);

        if (!$isScheduleManaged) {
            $changes['cancel_at_period_end'] = (bool) $stripeSub->cancel_at_period_end;

            if ($stripeSub->cancel_at_period_end && $subscription->cancelled_at === null) {
                $changes['cancelled_at'] = date('Y-m-d H:i:s');
            }
        }

        $changes['stripe_schedule_id'] = $stripeSub->schedule
            ? (is_string($stripeSub->schedule) ? $stripeSub->schedule : $stripeSub->schedule->id)
            : null;

        $updated = $this->subscriptionRepository->update($subscription->id, $changes);

        // Segment assignment is a non-critical, cross-cutting side effect —
        // never let it fail webhook processing (which Stripe would retry).
        try {
            $this->segmentHandler->onSubscriptionUpdated($updated ?? $subscription);
        } catch (\Throwable $e) {
            Logger::error('HandleSubscriptionUpdated: segment evaluation failed', [
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}