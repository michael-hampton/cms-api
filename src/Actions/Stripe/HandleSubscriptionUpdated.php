<?php

namespace App\Actions\Stripe;

use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Services\Billing\Stripe\StripeStatusMapper;
use Stripe\Event;

/**
 * Handles customer.subscription.updated
 *
 * Syncs period dates, status, and cancel_at_period_end.
 * Silently skips if the local record doesn't exist yet — a subsequent
 * customer.subscription.created event will create it.
 */
class HandleSubscriptionUpdated
{
    public function handle(Event $event): void
    {
        /** @var \Stripe\Subscription $stripeSub */
        $stripeSub = $event->data->object;

        $subscription = Subscription::where('payment_subscription_id', $stripeSub->id)->first();

        if ($subscription === null) {
            Logger::warning('HandleSubscriptionUpdated: subscription not found locally', [
                'payment_subscription_id' => $stripeSub->id,
            ]);
            return;
        }

        $subscription->status = StripeStatusMapper::subscriptionStatus($stripeSub->status);

        $subscription->current_period_start = $stripeSub->current_period_start
            ? date('Y-m-d H:i:s', $stripeSub->current_period_start)
            : $subscription->current_period_start;

        $subscription->current_period_end = $stripeSub->current_period_end
            ? date('Y-m-d H:i:s', $stripeSub->current_period_end)
            : $subscription->current_period_end;

        $subscription->cancel_at_period_end = (bool)$stripeSub->cancel_at_period_end;

        // Persist cancellation timestamp when Stripe signals a scheduled cancel
        if ($stripeSub->cancel_at_period_end && $subscription->cancelled_at === null) {
            $subscription->cancelled_at = date('Y-m-d H:i:s');
        }

        $subscription->save();
    }
}