<?php

namespace App\Actions\Stripe;

use App\Framework\Support\Logger;
use App\Models\Subscription;
use Stripe\Event;

/**
 * Handles customer.subscription.deleted
 *
 * Marks the local subscription as canceled and records the cancellation
 * timestamp if not already set.
 */
class HandleSubscriptionDeleted
{
    public function handle(Event $event): void
    {
        /** @var \Stripe\Subscription $stripeSub */
        $stripeSub = $event->data->object;

        $subscription = Subscription::where('payment_subscription_id', $stripeSub->id)->first();

        if ($subscription === null) {
            Logger::warning('HandleSubscriptionDeleted: subscription not found locally', [
                'payment_subscription_id' => $stripeSub->id,
            ]);
            return;
        }

        $subscription->status       = 'cancelled';
        $subscription->auto_renew   = false;
        $subscription->cancelled_at = $subscription->cancelled_at ?? date('Y-m-d H:i:s');

        // Set end_date from Stripe's ended_at (past) or current_period_end (future cancel)
        if ($stripeSub->ended_at) {
            $subscription->end_date = date('Y-m-d H:i:s', $stripeSub->ended_at);
        } elseif ($stripeSub->current_period_end) {
            $subscription->end_date = date('Y-m-d H:i:s', $stripeSub->current_period_end);
        }

        $subscription->save();
    }
}