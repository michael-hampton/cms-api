<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Subscription;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;
use DateTimeImmutable;
use DateTimeInterface;

final class SubscriptionAccountStateResolver
{
    private const RENEWAL_DUE_SEGMENTS = [
        'renewal_due_30_days',
        'renewal_due_7_days',
        'renewal_due_today',
    ];

    private const RECOVERABLE_SUSPENSION_CODES = [
        'payment_failure',
        'payment_failed',
        'past_due',
        'unpaid',
        'failed',
    ];

    public function __construct(
        private readonly ?SubscriptionSegmentRepository $subscriptionSegmentRepository = null,
    ) {
    }

    public function resolve(
        Subscription $subscription,
        ?DateTimeImmutable $now = null,
        ?string $segmentKey = null,
    ): array {
        $now ??= new DateTimeImmutable();
        $endDate = $this->date($subscription->end_date);
        $nextBillingDate = $this->date($subscription->next_billing_date);
        $pauseUntil = $this->date($subscription->pause_until);
        $status = (string)$subscription->status;
        $segmentKey ??= $this->activeSegmentKey($subscription);

        if (in_array($status, [
            SubscriptionStatus::SUSPENDED->value,
            SubscriptionStatus::PAST_DUE->value,
            SubscriptionStatus::UNPAID->value,
            SubscriptionStatus::FAILED->value,
        ], true)) {
            $suspensionCode = $this->suspensionCode($subscription, $status);
            $suspendedAt = $this->date($subscription->getAttribute('suspended_at'))
                ?? $this->date($subscription->getAttribute('suspended_on'))
                ?? $this->date($subscription->getAttribute('suspension_date'));
            $isRecoverable = in_array($suspensionCode, self::RECOVERABLE_SUSPENSION_CODES, true);

            return $this->state(
                key: 'suspended',
                group: 'action_required',
                label: $status === SubscriptionStatus::SUSPENDED->value ? 'Suspended' : 'Payment overdue',
                tone: 'danger',
                accent: 'red',
                copy: $this->suspensionCopy($suspensionCode, $isRecoverable),
                dateLabel: $suspendedAt ? 'Suspended on' : ($nextBillingDate ? 'Payment due' : null),
                date: $suspendedAt ?? $nextBillingDate,
                meta: [
                    'suspension_code' => $suspensionCode,
                    'suspension_reason' => $this->suspensionReason($subscription),
                    'is_recoverable' => $isRecoverable,
                ],
            );
        }

        if (in_array($status, [
            SubscriptionStatus::INCOMPLETE->value,
            SubscriptionStatus::RETRYING->value,
            SubscriptionStatus::PENDING->value,
            'processing',
        ], true)) {
            return $this->state(
                key: 'processing',
                group: 'action_required',
                label: 'Processing',
                tone: 'info',
                accent: 'blue',
                copy: 'We are confirming your payment.',
                dateLabel: null,
                date: null,
            );
        }

        if ($subscription->isCancellationScheduled()) {
            return $this->state(
                key: 'cancellation_scheduled',
                group: 'current',
                label: 'Cancellation scheduled',
                tone: 'warning',
                accent: 'amber',
                copy: $endDate ? 'Access continues until ' . $this->format($endDate) . '.' : 'Access continues until the current term ends.',
                dateLabel: 'Access ends',
                date: $endDate,
            );
        }

        if ($status === SubscriptionStatus::PAUSED->value) {
            return $this->state(
                key: 'paused',
                group: 'current',
                label: 'Paused',
                tone: 'info',
                accent: 'blue',
                copy: $pauseUntil
                    ? 'Paused until ' . $this->format($pauseUntil) . '.'
                    : 'This subscription is paused until you resume it.',
                dateLabel: $pauseUntil ? 'Paused until' : null,
                date: $pauseUntil,
            );
        }

        if ($status === SubscriptionStatus::CANCELLED->value) {
            $isStillEntitled = $endDate && $endDate > $now;

            return $this->state(
                key: 'cancelled',
                group: $isStillEntitled ? 'current' : 'previous',
                label: 'Cancelled',
                tone: 'neutral',
                accent: 'neutral',
                copy: $isStillEntitled
                    ? 'Access continues until ' . $this->format($endDate) . '.'
                    : ($endDate ? 'Access ended ' . $this->format($endDate) . '.' : 'This subscription has been cancelled.'),
                dateLabel: $endDate ? 'Access ends' : null,
                date: $endDate,
            );
        }

        if ($status === SubscriptionStatus::REPLACED->value) {
            return $this->state(
                key: 'replaced',
                group: 'previous',
                label: 'Renewed',
                tone: 'premium',
                accent: 'navy',
                copy: 'This subscription was replaced by a renewed subscription.',
                dateLabel: 'Previous term ended',
                date: $endDate,
            );
        }

        if ($status === SubscriptionStatus::EXPIRED->value || ($endDate && $endDate <= $now)) {
            return $this->state(
                key: 'expired',
                group: 'previous',
                label: 'Expired',
                tone: 'neutral',
                accent: 'neutral',
                copy: $endDate ? 'Access ended ' . $this->format($endDate) . '.' : 'This subscription has expired.',
                dateLabel: $endDate ? 'Expired on' : null,
                date: $endDate,
            );
        }

        if ($status === SubscriptionStatus::TRIALING->value) {
            $trialEnd = $subscription->getTrialEndsAt();

            $trialEndImmutable = $trialEnd
                ? DateTimeImmutable::createFromInterface($trialEnd)
                : null;

            return $this->state(
                key: 'trial',
                group: 'current',
                label: 'Trial',
                tone: 'info',
                accent: 'gold',
                copy: 'You are currently on a trial.',
                dateLabel: 'Trial ends',
                date: $trialEndImmutable,
                meta: [
                    'trial_ends_at' => $trialEndImmutable?->format('Y-m-d H:i:s'),
                    'is_trial' => true,
                ],
            );
        }

        if (
            $status === SubscriptionStatus::ACTIVE->value
            && in_array($segmentKey, self::RENEWAL_DUE_SEGMENTS, true)
        ) {
            if ($subscription->auto_renew) {
                if ($this->hasAcceptedRenewalOffer($subscription, $nextBillingDate, $now)) {
                    return $this->state(
                        key: 'renewal_offer_accepted',
                        group: 'current',
                        label: 'Renewing soon',
                        tone: 'success',
                        accent: 'green',
                        copy: 'Your renewal offer has been accepted and will be applied on renewal.',
                        dateLabel: $nextBillingDate ? 'Renews on' : null,
                        date: $nextBillingDate,
                    );
                }

                return $this->state(
                    key: 'renewing_soon',
                    group: 'current',
                    label: 'Renewing soon',
                    tone: 'success',
                    accent: 'green',
                    copy: $nextBillingDate
                        ? 'Renews automatically on ' . $this->format($nextBillingDate) . '.'
                        : 'This subscription will renew automatically soon.',
                    dateLabel: $nextBillingDate ? 'Renews on' : null,
                    date: $nextBillingDate,
                );
            }

            return $this->state(
                key: 'expiring_soon',
                group: 'current',
                label: 'Expiring soon',
                tone: 'warning',
                accent: 'amber',
                copy: $endDate
                    ? 'Access ends on ' . $this->format($endDate) . '.'
                    : 'This subscription will expire soon.',
                dateLabel: $endDate ? 'Expires on' : null,
                date: $endDate,
            );
        }

        return $this->state(
            key: 'active',
            group: 'current',
            label: $status === SubscriptionStatus::TRIALING->value ? 'Trial' : 'Active',
            tone: 'success',
            accent: 'gold',
            copy: $subscription->auto_renew ? 'Active and auto-renewing.' : 'Active for the current term.',
            dateLabel: $subscription->auto_renew ? 'Renews on' : 'Access ends',
            date: $subscription->auto_renew ? ($nextBillingDate ?? $endDate) : $endDate,
        );
    }

    private function suspensionCode(Subscription $subscription, string $status): string
    {
        $code = $subscription->getAttribute('suspension_code')
            ?? $subscription->getAttribute('suspension_reason_code')
            ?? $subscription->getAttribute('suspension_reason');

        if (is_string($code) && $code !== '') {
            return strtolower(trim($code));
        }

        return match ($status) {
            SubscriptionStatus::PAST_DUE->value,
            SubscriptionStatus::UNPAID->value,
            SubscriptionStatus::FAILED->value => 'payment_failure',
            default => 'unknown',
        };
    }

    private function suspensionReason(Subscription $subscription): ?string
    {
        $reason = $subscription->getAttribute('suspension_reason_label')
            ?? $subscription->getAttribute('suspension_reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    private function suspensionCopy(string $suspensionCode, bool $isRecoverable): string
    {
        if ($isRecoverable) {
            return 'Your subscription is suspended because payment could not be collected.';
        }

        return match ($suspensionCode) {
            'fraud', 'fraud_hold', 'compliance', 'compliance_hold' => 'Your subscription is suspended and needs support review.',
            default => 'Your subscription is suspended. Manage your subscription for more details.',
        };
    }

    private function hasAcceptedRenewalOffer(
        Subscription $subscription,
        ?DateTimeImmutable $nextBillingDate,
        DateTimeImmutable $now,
    ): bool {
        if (!$nextBillingDate || $nextBillingDate <= $now) {
            return false;
        }

        if (!empty($subscription->renewed_from_subscription_id)) {
            return false;
        }

        return !empty($subscription->subscription_plan_pricing_id)
            && !empty($subscription->offer_type);
    }

    private function activeSegmentKey(Subscription $subscription): ?string
    {
        if (empty($subscription->id)) {
            return null;
        }

        $repository = $this->subscriptionSegmentRepository
            ?? new SubscriptionSegmentRepository();

        return $repository->findActive((int)$subscription->id)?->segment?->key;
    }

    private function state(
        string $key,
        string $group,
        string $label,
        string $tone,
        string $accent,
        string $copy,
        ?string $dateLabel,
        ?DateTimeImmutable $date,
        array $meta = [],
    ): array {
        return array_merge([
            'key' => $key,
            'group' => $group,
            'label' => $label,
            'tone' => $tone,
            'accent' => $accent,
            'copy' => $copy,
            'date_label' => $dateLabel,
            'date_value' => $date ? $this->format($date) : null,
        ], $meta);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value);
        }

        return null;
    }

    private function format(DateTimeImmutable $date): string
    {
        return $date->format('j M Y');
    }
}
