<?php

namespace App\Services\Billing\Order;

use App\Repositories\Billing\OrderRepository;

class OrderStateManager
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
    )
    {
    }

    public function markPaid(int $orderId): void
    {
        $this->orderRepository->update($orderId, [
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
    }
}
