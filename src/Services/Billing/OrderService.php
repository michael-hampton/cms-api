<?php

namespace App\Services\Billing;

use App\Framework\Support\Cache\Cache;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Order;
use App\Repositories\Billing\OrderRepository;

/**
 * Read-only order lookups used by CRM and account-management surfaces.
 *
 * Order creation, updates, cancellation, and refunds live in
 * Order\OrderCreationService and Order\OrderUpdateService — this class only
 * retains the query methods still called from production code.
 */
class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {
    }

    public function getOrderById(int $id): ?Order
    {
        return $this->orderRepository->getOrderById($id);
    }

    public function getOrderByNumber(string $orderNumber): ?Order
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if ($order) {
            $order->load(['items', 'user', 'item.product']);
        }
        return $order;
    }

    public function searchForCrm(int $siteId, array $filters = []): array
    {
        return $this->orderRepository->searchForCrm($siteId, $filters);
    }

    public function getOrdersByStatus(string $status): Collection
    {
        return $this->orderRepository->getByStatus($status);
    }

    public function getOrdersByUser(int $userId, ?int $limit = null): Collection
    {
        return $this->orderRepository->getByUser($userId, $limit);
    }

    public function getTotalRevenue(?string $startDate = null, ?string $endDate = null): float
    {
        $cacheKey = "revenue_{$startDate}_{$endDate}";

        return Cache::remember($cacheKey, 3600, function () use ($startDate, $endDate) {
            return $this->orderRepository->getTotalRevenue($startDate, $endDate);
        });
    }

    public function find(int $orderId): Model
    {
        return $this->orderRepository->find($orderId);
    }
}
