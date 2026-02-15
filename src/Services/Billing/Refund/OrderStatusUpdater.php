<?php

namespace App\Services\Billing\Refund;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\RefundRepository;

class OrderStatusUpdater
{
    public function __construct(
        private readonly RefundRepository $refundRepository,
        private readonly OrderRepository  $orderRepository
    )
    {
    }

    public function updateAfterRefund(Order $order): void
    {
        $totalRefunded = $this->refundRepository->getTotalRefundedAmount($order->id);

        $isFullyRefunded = $totalRefunded >= $order->total;

        $this->orderRepository->update($order->id, [
            'status' => $isFullyRefunded
                ? OrderStatus::REFUNDED->value
                : OrderStatus::PARTIALLY_REFUNDED->value,
            'payment_status' => PaymentStatus::REFUNDED->value
        ]);
    }
}