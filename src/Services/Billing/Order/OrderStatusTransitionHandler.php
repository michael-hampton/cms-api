<?php

namespace App\Services\Billing\Order;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;

class OrderStatusTransitionHandler
{
    public function fillStatusFields(array $data, Order $currentOrder): array
    {
        if (!isset($data['status'])) {
            return $data;
        }

        $newStatus = OrderStatus::from($data['status']);

        return match ($newStatus) {
            OrderStatus::COMPLETED => $this->handleCompleted($data, $currentOrder),
            OrderStatus::CANCELLED => $this->handleCancelled($data, $currentOrder),
            OrderStatus::REFUNDED => $this->handleRefunded($data, $currentOrder),
            default => $data
        };
    }

    private function handleCompleted(array $data, Order $currentOrder): array
    {
        if (!$currentOrder->completed_at) {
            $data['completed_at'] = now_datetime();
        }
        return $data;
    }

    private function handleCancelled(array $data, Order $currentOrder): array
    {
        if (!$currentOrder->cancelled_at) {
            $data['cancelled_at'] = now_datetime();
        }
        return $data;
    }

    private function handleRefunded(array $data, Order $currentOrder): array
    {
        if (!$currentOrder->refunded_at) {
            $data['refunded_at'] = now_datetime();
            $data['payment_status'] = PaymentStatus::REFUNDED->value;
        }
        return $data;
    }

    public function validateTransition(Order $order, OrderStatus $newStatus): void
    {
        if (!$this->canTransitionTo($order, $newStatus)) {
            throw new \Exception(
                "Cannot transition from {$order->status} to {$newStatus->value}"
            );
        }
    }

    private function canTransitionTo(Order $order, OrderStatus $newStatus): bool
    {
        $currentStatus = OrderStatus::from($order->status);

        return match ($currentStatus) {
            OrderStatus::PENDING => in_array($newStatus, [
                OrderStatus::PROCESSING,
                OrderStatus::COMPLETED,
                OrderStatus::CANCELLED
            ]),
            OrderStatus::PROCESSING => in_array($newStatus, [
                OrderStatus::COMPLETED,
                OrderStatus::CANCELLED
            ]),
            OrderStatus::COMPLETED => in_array($newStatus, [
                OrderStatus::REFUNDED
            ]),
            OrderStatus::CANCELLED, OrderStatus::REFUNDED => false,
        };
    }
}