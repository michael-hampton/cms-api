<?php

namespace App\Services\Subscriptions\Refunds;

use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use Exception;

/**
 * Data-aware strategy: performs a read-only repository call to fetch the last
 * payment. This is intentional — the strategy needs the payment's transaction
 * identifiers for the service layer to issue the provider refund.
 *
 * All monetary arithmetic uses bcmath (scale 6) to avoid float precision drift.
 * The returned amount is a PHP float suitable for the strategy boundary; the
 * provider layer is responsible for converting to the smallest currency unit.
 */
class ProRatedRefundStrategy implements RefundStrategy
{
    private const BCMATH_SCALE = 6;

    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly string            $reason = 'early_cancellation',
    )
    {
    }

    public function calculate(Subscription $subscription): RefundResult
    {
        $this->assertDatesPresent($subscription);
        $this->assertPriceValid($subscription);

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

        $price = number_format($subscription->price, self::BCMATH_SCALE, '.', '');
        $dailyRate = bcdiv($price, (string)$totalDays, self::BCMATH_SCALE);
        $refundAmount = bcmul($dailyRate, (string)$unusedDays, self::BCMATH_SCALE);

        $payment = $this->payments->getLastSubscriptionPayment($subscription->id);

        if (!$payment) {
            throw new Exception('No payment found for refund');
        }

        return new RefundResult(
            amount: (float)$refundAmount,
            type: 'pro_rated',
            meta: [
                'original_payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'payment_method' => $payment->payment_method,
                'payment_provider' => $payment->payment_provider,
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

    private function assertPriceValid(Subscription $subscription): void
    {
        if (!$subscription->price || $subscription->price <= 0) {
            throw new Exception(
                'Cannot calculate pro-rated refund: subscription price is invalid'
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
}