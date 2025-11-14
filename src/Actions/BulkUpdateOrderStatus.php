<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Models\Order;
use App\Repositories\OrderRepository;
use Exception;

class BulkUpdateOrderStatus
{
    private Database $database;

    public function __construct(
        private readonly OrderRepository         $orderRepository,
        ?Database                                $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(array $orderIds, string $status, ?int $userId = null): array
    {
        return $this->database->transaction(function () use ($orderIds, $status, $userId) {
            $updated = [];
            $failed = [];

            foreach ($orderIds as $orderId) {
                try {
                    $order = $this->orderRepository->find($orderId);

                    if (!$order) {
                        $failed[] = ['id' => $orderId, 'reason' => 'Order not found'];
                        continue;
                    }

                    if (isset($data['status']) && $data['status'] !== $order->status) {
                        try {
                            $order->changeStatus($data['status'], $userId, $data['status_notes'] ?? null);
                            unset($data['status']); // Remove from data array as it's already handled
                        } catch (\Exception $e) {
                            throw new Exception("Status change failed: " . $e->getMessage());
                        }
                    }

                    $this->handleStatusChange($order, $status);

                    $updatedOrder = $this->orderRepository->update($orderId, ['status' => $status]);

                    if ($updatedOrder) {
                        $updated[] = $orderId;
                    } else {
                        $failed[] = ['id' => $orderId, 'reason' => 'Update failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $orderId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'updated' => $updated,
                'failed' => $failed,
                'total' => count($orderIds)
            ];
        });
    }

    private function handleStatusChange(Order $order, string $newStatus): void
    {
        if ($newStatus === 'completed' && !$order->completed_at) {
            $order->completed_at = date('Y-m-d H:i:s');
        }

        if ($newStatus === 'cancelled' && !$order->cancelled_at) {
            $order->cancelled_at = date('Y-m-d H:i:s');
        }
    }
}