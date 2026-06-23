<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;

class PaymentRefundPreviewService
{
    public function __construct(
        private readonly PaymentRepository $payments,
    ) {
    }

    public function summaryForPayment(mixed $payment): array
    {
        $context = $this->contextForPayment($payment);
        $eligible = in_array((string)$payment->status, ['completed', 'paid'], true)
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

    public function subscriptionPreview(mixed $payment, Subscription $subscription): array
    {
        $summary = $this->summaryForPayment($payment);
        $maxRefundable = (float)($summary['max_refundable_amount'] ?? 0.0);

        [$totalDays, $usedDays, $unusedDays] = $this->periodDays($subscription);
        $suggested = $this->proRatedAmount($maxRefundable, $totalDays, $unusedDays);

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
            'available_actions' => $this->availableSubscriptionActions($maxRefundable, $suggested),
            'provider' => [
                'name' => $payment->payment_provider ?? 'stripe',
                'payment_intent_id' => $payment->payment_intent_id ?? null,
                'charge_id' => str_starts_with((string)($payment->transaction_id ?? ''), 'ch_') ? $payment->transaction_id : null,
                'invoice_id' => $payment->stripe_invoice_id ?? null,
            ],
            'warnings' => $this->subscriptionWarnings($payment, $subscription, $summary),
        ];
    }

    private function contextForPayment(mixed $payment): string
    {
        if (!empty($payment->subscription_id)) {
            return 'subscription';
        }

        if (!empty($payment->order_id)) {
            return 'order';
        }

        if ((float)($payment->amount ?? 0) < 0 || ($payment->status ?? null) === 'refunded') {
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

        if (!in_array((string)$payment->status, ['completed', 'paid'], true)) {
            return 'Only completed payments can be refunded.';
        }

        if ((float)$payment->amount <= 0) {
            return 'Refund rows and zero-value payments are not refundable.';
        }

        return null;
    }

    private function periodDays(Subscription $subscription): array
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

    private function availableSubscriptionActions(float $maxRefundable, float $suggested): array
    {
        $actions = ['cancel_at_period_end', 'cancel_immediately_no_refund'];

        if ($suggested > 0) {
            $actions[] = 'pro_rated';
        }

        if ($maxRefundable > 0) {
            $actions[] = 'full';
            $actions[] = 'manual';
        }

        return $actions;
    }

    private function subscriptionWarnings(mixed $payment, Subscription $subscription, array $summary): array
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
