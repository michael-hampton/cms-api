<?php

namespace App\Services\Recommendations\Signals;

use App\Models\Member;
use App\Repositories\Billing\OrderRepository;

class PurchaseSignalProvider
{
    private const MAX_PRODUCTS = 20;
    private const RECENCY_DAYS = 90;

    public function __construct(
        private readonly OrderRepository $orderRepository
    )
    {
    }

    public function getProductIds(int $memberId): array
    {
        $orders = $this->orderRepository->getByUser($memberId);

        $productIds = [];
        $itemCount = 0;

        // Get most recent order items first
        foreach ($orders as $order) {
            if ($itemCount >= self::MAX_PRODUCTS) {
                break;
            }

            $items = $order->items ?? [];
            foreach ($items as $item) {
                if ($itemCount >= self::MAX_PRODUCTS) {
                    break;
                }

                if (!empty($item->product_id)) {
                    $productIds[] = $item->product_id;
                    $itemCount++;
                }
            }
        }

        return array_unique($productIds);
    }
}