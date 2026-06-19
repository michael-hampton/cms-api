<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionCancellationReason;
use App\Models\Subscription;

final class SubscriptionCancellationFlowProvider
{
    public function for(Subscription $subscription): ?array
    {
        if (!$this->canCancel($subscription)) {
            return null;
        }

        $endDate = $subscription->end_date?->format('j M Y');

        return [
            'title' => 'Cancel subscription renewal',
            'action_label' => 'Cancel renewal',
            'effective_date' => $endDate,
            'review_copy' => $endDate
                ? "You will keep access until {$endDate}."
                : 'You will keep access until the current term ends.',
            'lost_benefits' => [
                'Access to future issues',
                'Member renewal pricing',
                'Digital archive access',
            ],
            'reasons' => array_map(
                static fn(SubscriptionCancellationReason $reason): array => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                    'requires_note' => $reason === SubscriptionCancellationReason::Other,
                ],
                SubscriptionCancellationReason::cases()
            ),
            'confirmation' => [
                'access_end_date' => $endDate,
                'further_payments' => 'No further renewal payment will be taken.',
                'refund_outcome' => 'Refund eligibility depends on your subscription terms.',
                'can_reactivate' => true,
            ],
            'endpoint' => "/press-stack/account/subscriptions/{$subscription->id}/cancel",
        ];
    }

    public function canCancel(Subscription $subscription): bool
    {
        return $subscription->isActive()
            && !$subscription->isCancellationScheduled()
            && !$subscription->isCancelled();
    }
}
