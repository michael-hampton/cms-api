<?php

namespace App\Services\Subscriptions;

use App\Models\CancellationReason;
use App\Models\Subscription;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;

final class SubscriptionCancellationFlowProvider
{
    public function __construct(
        private readonly CancellationReasonRepository $cancellationReasonRepository,
    ) {
    }

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
            'refund_message' => 'Refund eligibility depends on your subscription terms.',
            'lost_benefits' => [
                'Access to future issues',
                'Member renewal pricing',
                'Digital archive access',
            ],
            'reasons' => array_map(
                static fn (CancellationReason $reason): array => [
                    'value' => $reason->code,
                    'label' => $reason->label,
                    'requires_note' => (bool) $reason->requires_note,
                ],
                $this->cancellationReasonRepository->listActive()->all(),
            ),
            'confirmation' => [
                'access_end_date' => $endDate,
                'access_message' => $accessMessage,
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
