<?php

namespace App\Services\Reviews;

use App\Repositories\Billing\OrderRepository;

class VerifiedPurchaseResolver
{
    public function __construct(
        private readonly OrderRepository $orderRepository
    )
    {
    }

    public function isVerified(int $userId, int $productId): bool
    {
        return $this->orderRepository
            ->getByUser($userId)
            ->filter(function ($order) use ($productId) {
                if (!$order->isCompleted() || !$order->isPaid()) {
                    return false;
                }

                if (!$order->relationLoaded('items')) {
                    return false;
                }

                return $order->items
                        ->where('product_id', $productId)
                        ->count() > 0;
            })
            ->isNotEmpty();
    }
}