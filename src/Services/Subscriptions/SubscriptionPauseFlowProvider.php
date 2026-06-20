<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

final class SubscriptionPauseFlowProvider
{
    public function for(Subscription $subscription, string $endpoint): ?array
    {
        if (!$subscription->isActive() || $subscription->isCancellationScheduled()) {
            return null;
        }

        $isPrint = $subscription->isPrint();

        return [
            'title' => 'Pause subscription',
            'review_copy' => 'Your subscription will remain paused until you manually resume it.',
            'billing_copy' => 'Renewal billing will stop while the subscription is paused.',
            'access_copy' => $isPrint && !$subscription->includes_digital_access
                ? 'This subscription does not currently include digital access.'
                : 'Digital, premium newsletter and archive access will stop while the subscription is paused.',
            'delivery_copy' => $isPrint
                ? 'Print deliveries and issues already queued for fulfilment will continue. Use Pause print delivery separately to stop deliveries between dates.'
                : 'This digital subscription has no print deliveries.',
            'fulfilment_copy' => $isPrint
                ? 'Upcoming print fulfilment is not changed by this billing-level pause.'
                : 'There is no print fulfilment attached to this subscription.',
            'renewal_copy' => $subscription->auto_renew
                ? 'Automatic renewal will be disabled during the pause and restored when you resume.'
                : 'Automatic renewal is already disabled and will remain disabled when you resume.',
            'resume_copy' => 'You can resume at any time. Your next billing date may move by the number of days paused.',
            'duration_copy' => 'This pause has no automatic end date.',
            'restrictions_copy' => 'Upgrades and manual renewal are unavailable until the subscription is resumed. Cancellation remains available.',
            'confirm_label' => 'Confirm pause',
            'cancel_label' => 'Keep subscription active',
            'endpoint' => $endpoint,
        ];
    }
}
