<?php

namespace App\Services\Billing\Refund;

use App\Exceptions\Orders\RefundAmountExceedsRemainingException;
use App\Models\Order;
use App\Repositories\Billing\RefundRepository;

class RefundAmountValidator
{
    public function __construct(
        private readonly RefundRepository $refundRepository
    )
    {
    }

    public function validateAmount(Order $order, float $requestedAmount): void
    {
        $totalRefunded = $this->refundRepository->getTotalRefundedAmount($order->id);
        $remainingAmount = $order->total - $totalRefunded;

        if ($requestedAmount > $remainingAmount) {
            throw RefundAmountExceedsRemainingException::create($requestedAmount, $remainingAmount);
        }
    }

    public function getRemainingAmount(Order $order): float
    {
        $totalRefunded = $this->refundRepository->getTotalRefundedAmount($order->id);
        return round(max(0, $order->total - $totalRefunded), 2);
    }
}