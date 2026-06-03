<?php

namespace App\Services\Subscriptions\Refunds;

use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use Exception;

/**
 * Calculates a pro-rated refund based on the actual amount charged (taken from
 * the last payment record) rather than the plan's base price. This ensures
 * pricing-tier discounts, sale prices, and vouchers are all reflected correctly.
 *
 * Arithmetic uses PHP's native round() at 6 decimal places — bcmath is NOT
 * required and must NOT be re-introduced (not available in this environment).
 */
class ProRatedRefundStrategy implements RefundStrategy
{
    private const PRECISION = 6;

    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly string            $reason = 'early_cancellation',
    )
    {
    }

    public function calculate(Subscription $subscription): RefundResult
    {
        $this->assertDatesPresent($subscription);

        $now = new \DateTime();
        $endDate = $subscription->end_date;
        $paymentDate = $subscription->last_payment_date;

        $this->assertDatesOrdered($paymentDate, $endDate);

        $totalDays = (int)$paymentDate->diff($endDate)->days;
        $usedDays = (int)$paymentDate->diff($now)->days;
        $unusedDays = max(0, $totalDays - $usedDays);

        if ($totalDays === 0) {
            throw new Exception(
                'Cannot calculate pro-rated refund: billing period is zero days'
            );
        }

        // Fetch the payment first — we need its amount as the authoritative price.
        // This is intentional: the strategy is read-only and the service layer
        // issues the actual refund against the provider.
        $payment = $this->payments->getLastSubscriptionPayment($subscription->id);

        if (!$payment) {
            throw new Exception('No payment found for refund');
        }

        $this->assertPaymentAmountValid($payment->amount);

        if ($unusedDays === 0) {
            return new RefundResult(
                amount: 0.0,
                type: 'pro_rated',
                meta: [
                    'unused_days' => 0,
                    'total_days' => $totalDays,
                    'reason' => 'No unused time remaining',
                ],
                noRefundDue: true,
            );
        }

        // Use the actual charged amount — accounts for pricing tiers, sale prices,
        // and voucher discounts automatically.
        $paidAmount = round((float)$payment->amount, self::PRECISION);
        $dailyRate = round($paidAmount / $totalDays, self::PRECISION);
        $refundAmount = round($dailyRate * $unusedDays, self::PRECISION);

        return new RefundResult(
            amount: $refundAmount,
            type: 'pro_rated',
            meta: [
                'original_payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'payment_intent_id' => $payment->payment_intent_id,
                'stripe_invoice_id' => $payment->stripe_invoice_id,
                'provider_transaction_id' => $payment->payment_intent_id ?: $payment->transaction_id,
                'payment_method' => $payment->payment_method,
                'payment_provider' => $payment->payment_provider,
                'paid_amount' => $paidAmount,
                'unused_days' => $unusedDays,
                'total_days' => $totalDays,
                'reason' => $this->reason,
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    private function assertDatesPresent(Subscription $subscription): void
    {
        if (!$subscription->end_date || !$subscription->last_payment_date) {
            throw new Exception(
                'Cannot calculate pro-rated refund: missing dates'
            );
        }
    }

    private function assertDatesOrdered(\DateTime $paymentDate, \DateTime $endDate): void
    {
        if ($paymentDate >= $endDate) {
            throw new Exception(
                'Cannot calculate pro-rated refund: payment date is not before end date'
            );
        }
    }

    private function assertPaymentAmountValid(mixed $amount): void
    {
        if (!$amount || (float)$amount <= 0) {
            throw new Exception(
                'Cannot calculate pro-rated refund: payment amount is invalid'
            );
        }
    }
}
