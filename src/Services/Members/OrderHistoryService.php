<?php

namespace App\Services\Members;

use App\Models\Model;
use App\Repositories\Members\OrderHistoryRepository;

class OrderHistoryService
{
    public function __construct(
        private readonly OrderHistoryRepository $repository
    ) {}

    public function logCreated(int $orderId, array $data, ?int $userId = null): Model
    {
        return $this->repository->create([
            'order_id' => $orderId,
            'action' => 'created',
            'user_id' => $userId,
            'changes' => ['new_data' => $data],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function logStatusChanged(
        int $orderId,
        string $oldStatus,
        string $newStatus,
        ?int $userId = null,
        ?string $notes = null
    ): Model {
        return $this->repository->create([
            'order_id' => $orderId,
            'action' => 'status_changed',
            'user_id' => $userId,
            'changes' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ],
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function logCancelled(int $orderId, ?int $userId = null, ?string $reason = null): Model
    {
        return $this->repository->create([
            'order_id' => $orderId,
            'action' => 'cancelled',
            'user_id' => $userId,
            'notes' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function logRefunded(int $orderId, ?int $userId = null, ?string $reason = null): Model
    {
        return $this->repository->create([
            'order_id' => $orderId,
            'action' => 'refunded',
            'user_id' => $userId,
            'notes' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function logUpdated(int $orderId, array $oldData, array $newData, ?int $userId = null): Model
    {
        $changes = [];
        foreach ($newData as $key => $value) {
            if (isset($oldData[$key]) && $oldData[$key] !== $value) {
                $changes[$key] = [
                    'old' => $oldData[$key],
                    'new' => $value
                ];
            }
        }

        return $this->repository->create([
            'order_id' => $orderId,
            'action' => 'updated',
            'user_id' => $userId,
            'changes' => $changes,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function logItemsUpdated(int $orderId, ?int $userId = null): Model
    {
        return $this->repository->create([
            'order_id' => $orderId,
            'action' => 'items_updated',
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getOrderHistory(int $orderId): \App\Framework\Support\Collection
    {
        return $this->repository->getHistoryForOrder($orderId);
    }

    public function logRefundCreated(int $orderId, int $refundId, ?int $userId = null, ?string $reason = null): Model
    {
        return $this->repository->create([
            'order_id' => $orderId,
            'action' => 'refund_created',
            'user_id' => $userId,
            'changes' => [
                'refund_id' => $refundId,
                'reason' => $reason
            ],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}