<?php

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Subscriptions\BusinessDecisions\RefundOptionsService;
use App\Services\Subscriptions\BusinessDecisions\CancellationRefundCapCalculator;

class PaymentRefundPreviewService
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly RefundOptionsService $refundOptionsService,
        private readonly CancellationRefundCapCalculator $refundCapCalculator,
    ) {
    }

    public function summaryForPayment(mixed $payment): array
    {
        $context = $this->contextForPayment($payment);
        $eligible = in_array((string)$payment->status, [PaymentStatus::COMPLETED->value, PaymentStatus::PAID->value], true)
            && in_array($context, ['order', 'subscription'], true)
            && (float)$payment->amount > 0;

        $alreadyRefunded = $this->alreadyRefundedAmount((int)$payment->id);
        $maxRefundable = $eligible
            ? max(0.0, round((float)$payment->amount - $alreadyRefunded, 2))
            : null;

        return [
            'eligible' => $eligible && $maxRefundable > 0,
            'mode' => $eligible ? $context : null,
            'already_refunded_amount' => $alreadyRefunded,
            'max_refundable_amount' => $maxRefundable,
            'suggested_refund_amount' => $maxRefundable,
            'suggested_refund_type' => $context === 'subscription' ? 'pro_rated' : 'full',
            'reason_not_eligible' => $eligible ? null : $this->ineligibleReason($payment, $context),
        ];
    }

    public function subscriptionPreview(mixed $payment, mixed $subscription): array
    {
        $summary = $this->summaryForPayment($payment);
        $maxRefundable = (float)($summary['max_refundable_amount'] ?? 0.0);

        [$totalDays, $usedDays, $unusedDays] = $this->periodDays($subscription);
        $suggested = $this->proRatedAmount($maxRefundable, $totalDays, $unusedDays);
        $reasons = $this->resolveRefundReasons((int) $subscription->id, (float) $payment->amount, $maxRefundable);

        return [
            'payment_id' => (int)$payment->id,
            'subscription_id' => (int)$subscription->id,
            'member_id' => isset($subscription->member_id) ? (int)$subscription->member_id : null,
            'currency' => $payment->currency ?? $subscription->currency ?? 'GBP',
            'original_amount' => round((float)$payment->amount, 2),
            'already_refunded_amount' => $summary['already_refunded_amount'],
            'max_refundable_amount' => $maxRefundable,
            'period_start' => $this->formatDate($subscription->last_payment_date ?? $payment->paid_at ?? null),
            'period_end' => $this->formatDate($subscription->end_date ?? null),
            'total_days' => $totalDays,
            'used_days' => $usedDays,
            'unused_days' => $unusedDays,
            'suggested_refund_type' => $suggested > 0 ? 'pro_rated' : 'none',
            'suggested_refund_amount' => $suggested,
            'available_actions' => $this->availableSubscriptionActions($maxRefundable, $suggested, $reasons),
            'reasons' => $reasons,
            'provider' => [
                'name' => $payment->payment_provider ?? 'stripe',
                'payment_intent_id' => $payment->payment_intent_id ?? null,
                'charge_id' => str_starts_with((string)($payment->transaction_id ?? ''), 'ch_') ? $payment->transaction_id : null,
                'invoice_id' => $payment->stripe_invoice_id ?? null,
            ],
            'warnings' => $this->subscriptionWarnings($payment, $subscription, $summary, $reasons),
        ];
    }

    private function resolveRefundReasons(int $subscriptionId, float $paymentAmount, float $maxRefundable): array
    {
        try {
            $options = $this->refundOptionsService->forSubscription($subscriptionId);
        } catch (\Throwable) {
            return [];
        }

        $reasons = [];

        foreach ($options->reasons as $reason) {
            $policyCap = $this->refundCapCalculator->maxRefundableAmount(
                $paymentAmount,
                $reason->options->refundMaxPercent,
            );

            $reasons[] = array_merge($reason->toArray(), [
                'policy_max_refund_amount' => min($maxRefundable, $policyCap),
            ]);
        }

        return $reasons;
    }

    private function contextForPayment(mixed $payment): string
    {
        if (!empty($payment->subscription_id)) {
            return 'subscription';
        }

        if (!empty($payment->order_id)) {
            return 'order';
        }

        if ((float)($payment->amount ?? 0) < 0 || ($payment->status ?? null) === PaymentStatus::REFUNDED->value) {
            return 'refund';
        }

        return 'manual';
    }

    private function alreadyRefundedAmount(int $paymentId): float
    {
        if (!method_exists($this->payments, 'sumRefundsForOriginalPayment')) {
            return 0.0;
        }

        return round((float)$this->payments->sumRefundsForOriginalPayment($paymentId), 2);
    }

    private function ineligibleReason(mixed $payment, string $context): ?string
    {
        if (!in_array($context, ['order', 'subscription'], true)) {
            return 'Only order and subscription payments can be refunded from this row.';
        }

        if (!in_array((string)$payment->status, [PaymentStatus::COMPLETED->value, PaymentStatus::PAID->value], true)) {
            return 'Only completed payments can be refunded.';
        }

        if ((float)$payment->amount <= 0) {
            return 'Refund rows and zero-value payments are not refundable.';
        }

        return null;
    }

    private function periodDays(mixed $subscription): array
    {
        $start = $subscription->last_payment_date ?? null;
        $end = $subscription->end_date ?? null;

        if (!$start instanceof \DateTimeInterface || !$end instanceof \DateTimeInterface || $start >= $end) {
            return [null, null, null];
        }

        $now = new \DateTimeImmutable();
        $totalDays = max(1, (int)$start->diff($end)->days);
        $usedDays = min($totalDays, max(0, (int)$start->diff($now)->days));
        $unusedDays = max(0, $totalDays - $usedDays);

        return [$totalDays, $usedDays, $unusedDays];
    }

    private function proRatedAmount(float $maxRefundable, ?int $totalDays, ?int $unusedDays): float
    {
        if (!$totalDays || !$unusedDays || $maxRefundable <= 0) {
            return 0.0;
        }

        return min($maxRefundable, round(($maxRefundable / $totalDays) * $unusedDays, 2));
    }

    private function availableSubscriptionActions(float $maxRefundable, float $suggested, array $reasons): array
    {
        if ($reasons === []) {
            return array_values(array_filter([
                'cancel_at_period_end',
                'cancel_immediately_no_refund',
                $suggested > 0 && $maxRefundable > 0 ? 'pro_rated' : null,
                $maxRefundable > 0 ? 'full' : null,
                $maxRefundable > 0 ? 'manual' : null,
            ]));
        }

        $actions = [];
        $permits = static fn (array $reason, string $field): bool => (bool) ($reason['options'][$field] ?? false);
        $allows = static fn (string $field) => array_filter($reasons, static fn (array $reason) => $permits($reason, $field));

        if ($allows('allow_cancel_at_period_end')) {
            $actions[] = 'cancel_at_period_end';
        }
        if ($allows('allow_cancel_immediately_no_refund')) {
            $actions[] = 'cancel_immediately_no_refund';
        }
        if ($maxRefundable > 0 && $suggested > 0 && $allows('allow_pro_rated')) {
            $actions[] = 'pro_rated';
        }
        if ($maxRefundable > 0 && $allows('allow_full')) {
            $actions[] = 'full';
        }
        if ($maxRefundable > 0 && $allows('allow_manual')) {
            $actions[] = 'manual';
        }

        return $actions;
    }

    private function subscriptionWarnings(mixed $payment, mixed $subscription, array $summary, array $reasons): array
    {
        $warnings = [];

        if (($summary['max_refundable_amount'] ?? 0) <= 0) {
            $warnings[] = 'This payment has no remaining refundable balance.';
        }

        if (!$subscription->last_payment_date || !$subscription->end_date) {
            $warnings[] = 'The subscription billing period is incomplete, so pro-rata calculation may not be available.';
        }

        if (empty($payment->payment_intent_id) && empty($payment->transaction_id)) {
            $warnings[] = 'No provider payment intent or charge is stored for this payment.';
        }

        $refundableReasons = array_filter(
            $reasons,
            static fn (array $reason) => ($reason['options']['refund_max_percent'] ?? 0) > 0,
        );

        if ($reasons !== [] && $refundableReasons === []) {
            $warnings[] = 'No refund reasons currently permit a refund under the resolved Business Decision.';
        }

        return $warnings;
    }

    private function formatDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return $date ? (string)$date : null;
    }
}
