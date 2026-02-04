<?php

namespace App\Services\Billing\Order;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\OrderCreatedEvent;
use App\Framework\Database\Database;
use App\Models\Order;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\OrderHistoryService;

class OrderCreationService
{
    public function __construct(
        private readonly OrderRepository         $orderRepository,
        private readonly OrderItemRepository     $orderItemRepository,
        private readonly OrderAddressResolver    $addressResolver,
        private readonly OrderCalculationService $calculationService,
        private readonly OrderHistoryService     $historyService,
        private readonly OrderNumberGenerator    $numberGenerator,
        private readonly Database                $database,
        private readonly OrderMemberResolver     $memberResolver,
    )
    {
    }

    public function create(array $data, array $items, int $siteId): Order
    {
        return $this->database->transaction(function () use ($data, $items, $siteId) {
            // 1. Resolve member
            $member = $this->memberResolver->resolve($data, $siteId);
            if ($member) {
                $data['user_id'] = $member->id;
            }

            // 2. Resolve addresses
            $this->addressResolver->resolveAddresses($data, $member, $siteId);

            // Capture customer email BEFORE unsetting
            $customerEmail = $data['customer_email'] ?? null;

            // 3. Prepare order data
            $data = $this->prepareOrderData($data, $items, $siteId);

            // 4. Create order
            $order = $this->orderRepository->create($data);

            // 5. Create items
            $this->createOrderItems($order->id, $items);

            // 6. Log history
            $this->historyService->logCreated($order->id, $data, $data['user_id'] ?? null);

            // 7. Emit event (will trigger email via listener)
            event(new OrderCreatedEvent($order, $customerEmail ?? null));

            // 8. Return with relationships
            return $this->orderRepository->getOrderById($order->id);
        });
    }

    public function createMerchantOrder(
        array $data,
        array $items,
        int   $siteId,
        ?int  $merchantId = null
    ): Order
    {
        return $this->database->transaction(function () use ($data, $items, $siteId, $merchantId) {
            // Resolve member
            $member = $this->memberResolver->resolve($data, $siteId);
            if ($member) {
                $data['user_id'] = $member->id;
            }

            // Resolve addresses
            $this->addressResolver->resolveAddresses($data, $member, $siteId);

            // Prepare order data
            $data = $this->prepareOrderData($data, $items, $siteId, $merchantId);

            // CRITICAL: For merchant orders, do NOT recalculate totals
            // They're pre-calculated by PaymentAllocationService

            // Create order
            $order = $this->orderRepository->create($data);

            // Create items
            $this->createOrderItems($order->id, $items);

            // Log history
            $this->historyService->logCreated($order->id, $data, $data['user_id'] ?? null);

            // NOTE: No email sent for merchant orders
            // Parent checkout orchestrator handles unified confirmation

            return $this->orderRepository->getOrderById($order->id);
        });
    }

    private function prepareOrderData(
        array $data,
        array $items,
        int   $siteId,
        ?int  $merchantId = null
    ): array
    {

        // Remove customer fields (not part of orders table)
        unset($data['customer_name'], $data['customer_email'], $data['customer_phone']);

        // Generate order number if not provided
        if (empty($data['order_number'])) {
            $data['order_number'] = $this->numberGenerator->generate();
        }

        // Calculate totals (only if not already calculated)
        if (!isset($data['total'])) {
            $calculatedTotals = $this->calculationService->calculateOrderTotals($items, $data);
            $data = array_merge($data, $calculatedTotals);
        }

        // Set defaults
        $data['site_id'] = $siteId;
        $data['status'] = $data['status'] ?? OrderStatus::PENDING->value;
        $data['payment_status'] = $data['payment_status'] ?? PaymentStatus::UNPAID->value;

        if ($merchantId) {
            $data['merchant_id'] = $merchantId;
        }

        return $data;
    }

    private function createOrderItems(int $orderId, array $items): void
    {
        foreach ($items as $item) {

            $this->validateLineItems($item);

            $itemData = $item;
            $itemData['order_id'] = $orderId;

            // Calculate item totals if not provided
            if (!isset($itemData['subtotal'])) {
                $itemData['subtotal'] = $itemData['unit_price'] * $itemData['quantity'];
            }

            if (!isset($itemData['total'])) {
                $itemData['total'] = $itemData['subtotal'] + ($itemData['tax'] ?? 0);
            }

            $this->orderItemRepository->create($itemData);
        }
    }

    private function validateLineItems(array $item): void
    {
        if (!isset($item['unit_price']) || !isset($item['quantity'])) {
            throw new \InvalidArgumentException('Order item missing unit_price or quantity');
        }

        if (!is_numeric($item['unit_price']) || !is_numeric($item['quantity'])) {
            throw new \InvalidArgumentException('Order item unit_price and quantity must be numeric');
        }
    }
}