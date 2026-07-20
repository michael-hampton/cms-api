<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

/**
 * Calculates the maximum refund amount permitted by a resolved
 * cancellation-reason policy's refund_max_percent.
 *
 * Pure arithmetic — no I/O. The percent is always of the original
 * payment amount; callers that also track already-refunded balances
 * should take min(this, remainingRefundable).
 */
final class CancellationRefundCapCalculator
{
    public function maxRefundableAmount(float $paymentAmount, int $refundMaxPercent): float
    {
        if ($refundMaxPercent <= 0 || $paymentAmount <= 0) {
            return 0.0;
        }

        return round($paymentAmount * ($refundMaxPercent / 100), 2);
    }
}
