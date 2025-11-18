<?php

namespace App\Services;

use App\Framework\Database\Database;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\RefundConfirmation;
use App\Models\Order;
use App\Models\Refund;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RefundRepository;
use Exception;

class RefundService
{
    private Database $database;

    public function __construct(
        private readonly RefundRepository    $refundRepository,
        private readonly OrderRepository     $orderRepository,
        private readonly ProductRepository   $productRepository,
        private readonly OrderHistoryService $historyService,
        private readonly MailManager         $mailManager,
        ?Database                            $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function createRefund(array $data, ?int $userId = null): Refund
    {
        return $this->database->transaction(function () use ($data, $userId) {
            // Validate order exists
            $order = $this->orderRepository->find($data['order_id']);
            if (!$order) {
                throw new Exception('Order not found');
            }

            // Validate order can be refunded
            if (!$order->canBeRefunded()) {
                throw new Exception('Order cannot be refunded');
            }

            // Calculate refund amount if not provided
            if (!isset($data['refund_amount']) || $data['refund_amount'] <= 0) {
                $data['refund_amount'] = $this->calculateRefundAmount($data['items'] ?? []);
            }

            // Validate refund amount
            $totalRefunded = $this->refundRepository->getTotalRefundedAmount($order->id);
            $remainingAmount = $order->total - $totalRefunded;

            if ($data['refund_amount'] > $remainingAmount) {
                throw new Exception("Refund amount exceeds remaining order total. Available: {$remainingAmount}");
            }

            // Create refund record
            $refundData = [
                'order_id' => $data['order_id'],
                'refund_type' => $data['refund_type'] ?? 'full',
                'refund_amount' => $data['refund_amount'],
                'reason' => $data['reason'],
                'internal_notes' => $data['internal_notes'] ?? null,
                'notify_customer' => $data['notify_customer'] ?? true,
                'restock_items' => $data['restock_items'] ?? true,
                'status' => 'pending',
                'site_id' => $order->site_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $refund = $this->refundRepository->create($refundData);

            // Create refund items
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->createRefundItem($refund->id, $item);
                }
            }

            // Restock items if requested
            if ($data['restock_items'] ?? true) {
                $this->restockItems($refund->id);
            }

            // Process refund immediately
            $this->processRefund($refund->id, $userId);

            // Update order status
            $this->updateOrderStatus($order);

            // Log history
            $this->historyService->logRefundCreated($order->id, $refund->id, $order->user_id, $data['reason']);

            // Send notification
            if ($data['notify_customer'] ?? true) {
                $this->sendRefundNotification($refund);
            }

            return $this->refundRepository->find($refund->id);
        });
    }

    private function calculateRefundAmount(array $items): float
    {
        return array_reduce($items, function ($sum, $item) {
            return $sum + ($item['refund_amount'] ?? 0);
        }, 0.0);
    }

    private function createRefundItem(int $refundId, array $itemData): void
    {
        $refundItemData = [
            'refund_id' => $refundId,
            'order_item_id' => $itemData['id'] ?? null,
            'product_id' => $itemData['product_id'] ?? null,
            'product_name' => $itemData['product_name'],
            'quantity' => $itemData['quantity'] ?? 0,
            'refund_quantity' => $itemData['refund_quantity'] ?? $itemData['quantity'] ?? 0,
            'unit_price' => $itemData['unit_price'] ?? 0,
            'refund_amount' => $itemData['refund_amount'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->refundRepository->createRefundItem($refundItemData);
    }

    private function restockItems(int $refundId): void
    {
        $items = $this->refundRepository->getRefundItems($refundId);

        foreach ($items as $item) {
            if ($item->product_id && $item->refund_quantity > 0) {
                $product = $this->productRepository->find($item->product_id);
                if ($product) {
                    $newQuantity = $product->stock_quantity + $item->refund_quantity;
                    $this->productRepository->update($item->product_id, [
                        'stock_quantity' => $newQuantity
                    ]);
                }
            }
        }
    }

    public function processRefund(int $refundId, ?int $userId = null): bool
    {
        return $this->database->transaction(function () use ($refundId, $userId) {
            $refund = $this->refundRepository->find($refundId);
            if (!$refund) {
                throw new Exception('Refund not found');
            }

            if (!$refund->isPending()) {
                throw new Exception('Refund has already been processed');
            }

            // Update refund status
            $this->refundRepository->updateRefundStatus($refundId, 'processed', $userId);

            return true;
        });
    }

    private function updateOrderStatus(Order $order): void
    {
        $totalRefunded = $this->refundRepository->getTotalRefundedAmount($order->id);

        // If fully refunded, update order status
        $this->orderRepository->update($order->id, [
            'status' => $totalRefunded >= $order->total ? 'refunded' : 'partially_refunded',
            'payment_status' => 'refunded'
        ]);
    }

    private function sendRefundNotification(Refund $refund): void
    {
        try {
            $order = $this->orderRepository->find($refund->order_id);
            if (!$order || !$order->user) {
                return;
            }

            $customerEmail = $order->user->email;
            if ($customerEmail) {
                $this->mailManager->to($customerEmail)->send(new RefundConfirmation($refund, $order));
            }
        } catch (Exception $e) {
            Logger::error("Failed to send refund notification", [
                'refund_id' => $refund->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getRefundsByOrder(int $orderId): \App\Framework\Support\Collection
    {
        return $this->refundRepository->findByOrderId($orderId);
    }

    public function cancelRefund(int $refundId, ?int $userId = null): bool
    {
        return $this->database->transaction(function () use ($refundId, $userId) {
            $refund = $this->refundRepository->find($refundId);
            if (!$refund) {
                throw new Exception('Refund not found');
            }

            if (!$refund->isPending()) {
                throw new Exception('Only pending refunds can be cancelled');
            }

            return $this->refundRepository->updateRefundStatus($refundId, 'cancelled', $userId);
        });
    }

    public function getRemainingRefundableAmount(int $orderId): float
    {
        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            return 0.0;
        }

        $totalRefunded = $this->refundRepository->getTotalRefundedAmount($orderId);
        return max(0, $order->total - $totalRefunded);
    }
}