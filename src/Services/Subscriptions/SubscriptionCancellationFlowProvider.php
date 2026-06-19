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

        $endDate = $this->formatDate($subscription->end_date);
        $accessMessage = $endDate
            ? "You will keep access until {$endDate}."
            : 'You will keep access until the current term ends.';

        return [
            'title' => 'Cancel subscription renewal',
            'action_label' => 'Cancel renewal',
            'effective_date' => $endDate,
            'review_copy' => $accessMessage,
            'access_message' => $accessMessage,
            'billing_message' => 'No further renewal payment will be taken.',
            'refund_message' => 'No refund is due because access continues until the end of the current term.',
            'lost_benefits' => $this->lostBenefits($subscription),
            'alternatives' => [],
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
                'access_message' => $accessMessage,
                'further_payments' => 'No further renewal payment will be taken.',
                'refund_outcome' => 'No refund is due because access continues until the end of the current term.',
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

    private function lostBenefits(Subscription $subscription): array
    {
        $benefits = ['Access to future subscriber-only issues'];

        if ($subscription->isDigital() || $subscription->includes_digital_access) {
            $benefits[] = 'Digital archive access';
        }

        if ($subscription->premiumAccess(true)->where('is_active', true)->count() > 0) {
            $benefits[] = 'Premium subscriber benefits';
        }

        return $benefits;
    }

    private function formatDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('j M Y');
        }

        if (is_string($date) && $date !== '') {
            $timestamp = strtotime($date);
            return $timestamp === false ? null : date('j M Y', $timestamp);
        }

        return null;
    }
}
