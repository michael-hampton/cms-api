<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Subscription;
use DateTimeImmutable;
use DateTimeInterface;

final class SubscriptionAccountStateResolver
{
    public function resolve(Subscription $subscription, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $endDate = $this->date($subscription->end_date);
        $nextBillingDate = $this->date($subscription->next_billing_date);
        $status = (string)$subscription->status;

        if (in_array($status, [
            SubscriptionStatus::SUSPENDED->value,
            SubscriptionStatus::PAST_DUE->value,
            SubscriptionStatus::UNPAID->value,
            SubscriptionStatus::FAILED->value,
        ], true)) {
            return $this->state(
                key: 'suspended',
                group: 'action_required',
                label: $status === SubscriptionStatus::SUSPENDED->value ? 'Suspended' : 'Payment overdue',
                tone: 'danger',
                accent: 'red',
                copy: 'Payment or support action is required.',
                dateLabel: $nextBillingDate ? 'Payment due' : null,
                date: $nextBillingDate,
            );
        }

        if (in_array($status, [
            SubscriptionStatus::INCOMPLETE->value,
            SubscriptionStatus::RETRYING->value,
            SubscriptionStatus::PENDING->value,
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
                dateLabel: 'Access until',
                date: $endDate,
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
                dateLabel: $isStillEntitled ? 'Access until' : 'Ended',
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
                dateLabel: 'Ended',
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

        $daysUntilEnd = $this->daysUntil($endDate, $now);
        $daysUntilBilling = $this->daysUntil($nextBillingDate, $now);

        if ($subscription->auto_renew && $daysUntilBilling !== null && $daysUntilBilling <= 30) {
            return $this->state(
                key: 'renewing_soon',
                group: 'current',
                label: 'Renewing soon',
                tone: 'success',
                accent: 'green',
                copy: 'Renews automatically on ' . $this->format($nextBillingDate) . '.',
                dateLabel: 'Renews',
                date: $nextBillingDate,
            );
        }

        if (!$subscription->auto_renew && $daysUntilEnd !== null && $daysUntilEnd <= 30) {
            return $this->state(
                key: 'expiring_soon',
                group: 'current',
                label: 'Expiring soon',
                tone: 'warning',
                accent: 'amber',
                copy: 'Access ends on ' . $this->format($endDate) . '.',
                dateLabel: 'Access ends',
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
            dateLabel: $subscription->auto_renew ? 'Renews' : 'Access until',
            date: $subscription->auto_renew ? ($nextBillingDate ?? $endDate) : $endDate,
        );
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
    ): array {
        return [
            'key' => $key,
            'group' => $group,
            'label' => $label,
            'tone' => $tone,
            'accent' => $accent,
            'copy' => $copy,
            'date_label' => $dateLabel,
            'date_value' => $date ? $this->format($date) : null,
        ];
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

    private function daysUntil(?DateTimeImmutable $date, DateTimeImmutable $now): ?int
    {
        if (!$date || $date < $now) {
            return null;
        }

        return (int)$now->diff($date)->days;
    }

    private function format(DateTimeImmutable $date): string
    {
        return $date->format('j M Y');
    }
}
