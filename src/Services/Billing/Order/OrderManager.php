<?php

namespace App\Services\Billing\Order;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Order;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;

class OrderManager
{
    public function __construct(
        private readonly OrderRepository     $orderRepository,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly Database            $database
    )
    {
    }

    public function findById(int $id): ?Order
    {
        return $this->orderRepository->getOrderById($id);
    }

    public function findByNumber(string $orderNumber): ?Order
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if ($order) {
            $order->load(['items', 'user', 'item.product']);
        }
        return $order;
    }

    public function getAll(int $page = 1, int $perPage = 50): array
    {
        return $this->orderRepository->getAll($page, $perPage);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->orderRepository->getByStatus($status);
    }

    public function getByUser(int $userId, ?int $limit = null): Collection
    {
        return $this->orderRepository->getByUser($userId, $limit);
    }

    public function delete(int $orderId): bool
    {
        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            throw new \Exception('Order not found');
        }

        return $this->database->transaction(function () use ($order) {
            return $order->delete();
        });
    }

    public function updateOrderStatus(int $orderId, string $orderStatus, string $paymentStatus)
    {
        return $this->orderRepository->update($orderId, [
            'status' => $orderStatus,
            'payment_status' => $paymentStatus
        ]);
    }

    public function find(int $orderId)
    {
        return $this->orderRepository->find($orderId);
    }
}