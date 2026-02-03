<?php

namespace App\Services\Billing\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\OrderCancelledEvent;
use App\Events\Orders\OrderRefundedEvent;
use App\Events\Orders\OrderUpdatedEvent;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Order;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\Order\OrderAddressResolver;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\OrderHistoryService;

class OrderUpdateService
{
    public function __construct(
        private readonly OrderRepository              $orderRepository,
        private readonly OrderItemRepository          $orderItemRepository,
        private readonly MemberRepository             $memberRepository,
        private readonly OrderAddressResolver         $addressResolver,
        private readonly OrderCalculationService      $calculationService,
        private readonly OrderHistoryService          $historyService,
        private readonly OrderStatusTransitionHandler $statusHandler,
        private readonly Database                     $database
    )
    {
    }

    /**
     * Update an existing order.
     *
     * @param int $id Order ID
     * @param array $data Update data
     * @param int|null $siteId
     * @param int|null $userId User making the update (for history)
     * @return Order Updated order with relationships
     * @throws \Exception If order not found or update fails
     */
    public function update(int $id, array $data, ?int $siteId = null, ?int $userId = null): Order
    {
        return $this->database->transaction(function () use ($id, $data, $siteId, $userId) {
            $order = $this->orderRepository->find($id);

            if (!$order) {
                throw new \Exception("Order not found");
            }

            $oldData = $order->toArray();

            // Validate and handle status transitions
            if (isset($data['status']) && $data['status'] !== $order->status) {
                $newStatus = OrderStatus::from($data['status']);
                $this->statusHandler->validateTransition($order, $newStatus);
                $data = $this->statusHandler->fillStatusFields($data, $order);
            }

            // Get member if exists
            $member = null;
            if ($order->user_id) {
                $member = $this->memberRepository->find($order->user_id);
            }

            // Resolve addresses if provided
            if (isset($data['shipping_address']) || isset($data['shipping_address_id']) ||
                isset($data['billing_address']) || isset($data['billing_address_id'])) {
                $this->addressResolver->resolveAddresses($data, $member, $siteId ?? $order->site_id);
            }

            // Perform update
            $updatedOrder = $this->orderRepository->update($id, $data);

            if (!$updatedOrder) {
                throw new \Exception("Failed to update order");
            }

            $this->updateItems($id, $data['items'] ?? [], $userId);

            // Log history
            $this->historyService->logUpdated($id, $oldData, $data, $userId);

            // Emit event
            event(new OrderUpdatedEvent($updatedOrder, $oldData, $userId));

            return $this->orderRepository->getOrderById($id);
        });
    }

    /**
     * Update order items and recalculate totals.
     *
     * @param int $orderId
     * @param array $items New items array
     * @param int|null $userId User making the update
     * @return Order Updated order
     * @throws \Exception If order not found
     */
    public function updateItems(int $orderId, array $items, ?int $userId = null): bool
    {
        return $this->database->transaction(function () use ($orderId, $items, $userId) {
            $order = $this->orderRepository->find($orderId);

            if (!$order) {
                throw new \Exception("Order not found");
            }

            // Delete existing items
            $this->orderItemRepository->deleteByOrderId($orderId);

            // Create new items
            foreach ($items as $item) {
                $this->createOrderItem($orderId, $item);
            }

            // Recalculate order totals
            $calculatedTotals = $this->calculationService->calculateOrderTotals(
                $items,
                $order->toArray()
            );
            $this->orderRepository->update($orderId, $calculatedTotals);

            // Log history
            $this->historyService->logItemsUpdated($orderId, $userId);

            return true;
        });
    }

    /**
     * Cancel an order.
     *
     * @param int $orderId
     * @param string|null $reason Cancellation reason
     * @param int|null $userId User performing cancellation
     * @return Order Cancelled order
     * @throws \Exception If order not found or cannot be cancelled
     */
    public function cancel(int $orderId, ?string $reason = null, ?int $userId = null): Order
    {
        return $this->database->transaction(function () use ($orderId, $reason, $userId) {
            $order = $this->orderRepository->find($orderId);

            if (!$order) {
                throw new \Exception("Order not found");
            }

            if (!$order->canBeCancelled()) {
                throw new \Exception("Order cannot be cancelled in its current status");
            }

            $updateData = [
                'status' => OrderStatus::CANCELLED->value,
                'cancelled_at' => now_datetime()
            ];

            if ($reason) {
                $updateData['admin_notes'] = ($order->admin_notes ? $order->admin_notes . "\n\n" : '')
                    . "Cancellation reason: " . $reason;
            }

            $updatedOrder = $this->update($orderId, $updateData, null, $userId);

            // Log cancellation
            $this->historyService->logCancelled($orderId, $userId, $reason);

            // Emit event
            event(new OrderCancelledEvent($updatedOrder, $reason, $userId));

            return $updatedOrder;
        });
    }

    /**
     * Mark order as completed.
     *
     * @param int $orderId
     * @param int|null $userId User performing completion
     * @return Order Completed order
     */
    public function complete(int $orderId, ?int $userId = null): Order
    {
        return $this->update($orderId, [
            'status' => OrderStatus::COMPLETED->value,
            'completed_at' => now_datetime()
        ], null, $userId);
    }

    /**
     * Refund an order.
     *
     * @param int $orderId
     * @param string|null $reason Refund reason
     * @param int|null $userId User performing refund
     * @return Order Refunded order
     * @throws \Exception If order not found
     */
    public function refund(int $orderId, ?string $reason = null, ?int $userId = null): Order
    {
        return $this->database->transaction(function () use ($orderId, $reason, $userId) {
            $order = $this->orderRepository->find($orderId);

            if (!$order) {
                throw new \Exception("Order not found");
            }

            $updateData = [
                'status' => OrderStatus::REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ];

            if ($reason) {
                $updateData['admin_notes'] = ($order->admin_notes ? $order->admin_notes . "\n\n" : '')
                    . "Refund reason: " . $reason;
            }

            $updatedOrder = $this->update($orderId, $updateData, null, $userId);

            // Log refund
            $this->historyService->logRefunded($orderId, $userId, $reason);

            // Emit event
            event(new OrderRefundedEvent($updatedOrder, $reason, $userId));

            return $updatedOrder;
        });
    }

    /**
     * Create an order item.
     *
     * @param int $orderId
     * @param array $itemData
     * @return mixed Created item
     */
    private function createOrderItem(int $orderId, array $itemData)
    {
        $itemData['order_id'] = $orderId;

        // Calculate item totals if not provided
        if (!isset($itemData['subtotal'])) {
            $itemData['subtotal'] = $itemData['unit_price'] * $itemData['quantity'];
        }

        if (!isset($itemData['total'])) {
            $itemData['total'] = $itemData['subtotal'] + ($itemData['tax'] ?? 0);
        }

        return $this->orderItemRepository->create($itemData);
    }
}