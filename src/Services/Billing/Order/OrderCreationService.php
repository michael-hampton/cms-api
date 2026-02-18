<?php

namespace App\Services\Billing\Order;

use App\Enums\Orders\OrderLineStatus;
use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\OrderCreatedEvent;
use App\Framework\Database\Database;
use App\Models\Order;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\OrderHistoryService;
use App\Services\Commission\CommissionService;
use App\Services\Product\MerchantTransactionService;

class OrderCreationService
{
    public function __construct(
        private readonly OrderRepository            $orderRepository,
        private readonly OrderItemRepository        $orderItemRepository,
        private readonly OrderAddressResolver       $addressResolver,
        private readonly OrderCalculationService    $calculationService,
        private readonly OrderHistoryService        $historyService,
        private readonly OrderNumberGenerator       $numberGenerator,
        private readonly Database                   $database,
        private readonly OrderMemberResolver        $memberResolver,
        private readonly CommissionService          $commissionService,
        private readonly MerchantRepository         $merchantRepository,
        private readonly MerchantTransactionService $merchantTransactionService,
        private readonly ProductRepository          $productRepository
    )
    {
    }

    public function create(array $data, array $items, int $siteId, array $discountBreakdown = []): Order
    {
        return $this->database->transaction(function () use ($data, $items, $siteId, $discountBreakdown) {
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
            $data = $this->prepareOrderData($data, $items, $siteId, null, $discountBreakdown);

            // 4. Create order
            $order = $this->orderRepository->create($data);

            // 5. Create items
            $this->createOrderItems($order->id, $items);

            // FIX: Credit merchants with their net amounts
            $this->creditMerchantsForOrder($order->id);

            // 6. Log history
            $this->historyService->logCreated($order->id, $data, $data['user_id'] ?? null);

            // 7. Emit event (will trigger email via listener)
            if ($_ENV['APP_ENV'] !== 'testing') {
                event(new OrderCreatedEvent($order, $customerEmail ?? null));
            }

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

            if ($merchantId) {
                // Calculate total net amount for this merchant
                $orderItems = $this->orderItemRepository->getByOrderId($order->id);
                $totalNetAmount = 0;

                foreach ($orderItems as $orderItem) {
                    $totalNetAmount += $orderItem->net_amount ?? 0;
                }

                if ($totalNetAmount > 0) {
                    $this->merchantTransactionService->credit($merchantId, $totalNetAmount, $order->id);
                }
            }

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
        ?int  $merchantId = null,
        array $discountBreakdown = []
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

        if (!empty($discountBreakdown)) {
            $data['offer_discount'] = $discountBreakdown['offer_discount'] ?? 0;
            $data['tiered_discount'] = $discountBreakdown['tiered_discount'] ?? 0;
            $data['voucher_discount'] = $discountBreakdown['voucher_discount'] ?? 0;
            $data['reward_discount'] = $discountBreakdown['reward_discount'] ?? 0;
            $data['discount'] = $discountBreakdown['total_discount'] ?? 0;
            $data['merchant_funded'] = $discountBreakdown['merchant_funded'] ?? 0;
            $data['platform_funded'] = $discountBreakdown['platform_funded'] ?? 0;
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

            $itemData['status'] = $item['order_line_status'] ?? OrderLineStatus::READY_TO_SHIP->value;
            $itemData['expected_ship_date'] = $item['expected_ship_date'] ?? null;
            $itemData['quantity_allocated'] = $item['quantity_allocated'] ?? $itemData['quantity'];

            // CRITICAL: Calculate commission ONLY if merchant_id and product_id are present
            if (!empty($item['merchant_id']) && !empty($item['product_id'])) {
                $product = $this->productRepository->find($item['product_id']);
                $merchant = $this->merchantRepository->find($item['merchant_id']);

                if ($product && $merchant) {
                    $rate = $this->commissionService->determineRate($product, $merchant);
                    $grossAmount = $itemData['subtotal'];
                    $result = $this->commissionService->calculate($grossAmount, $rate);

                    $itemData['commission_rate'] = $result['rate'];
                    $itemData['commission_amount'] = $result['commission_amount'];
                    $itemData['net_amount'] = $result['net_amount'];
                } else {
                    // Fallback: no commission
                    $itemData['commission_rate'] = 0.0000;
                    $itemData['commission_amount'] = 0.00;
                    $itemData['net_amount'] = $itemData['subtotal'];
                }
            } else {
                // No merchant or product: system items, subscription items, etc.
                $itemData['commission_rate'] = 0.0000;
                $itemData['commission_amount'] = 0.00;
                $itemData['net_amount'] = $itemData['subtotal'];
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

    /**
     * Credit all merchants for their order items
     * Groups items by merchant_id and credits each merchant once with their total net amount
     */
    private function creditMerchantsForOrder(int $orderId): void
    {
        $orderItems = $this->orderItemRepository->getByOrderId($orderId);

        // Group net amounts by merchant
        $merchantTotals = [];

        foreach ($orderItems as $item) {

            // Skip items without merchant (system items, subscriptions, etc.)
            if (!$item->merchant_id) {
                continue;
            }

            $merchantId = $item->merchant_id;
            $netAmount = $item->net_amount;

            if (!isset($merchantTotals[$merchantId])) {
                $merchantTotals[$merchantId] = 0;
            }

            $merchantTotals[$merchantId] += $netAmount;
        }

        // Credit each merchant
        foreach ($merchantTotals as $merchantId => $totalNetAmount) {
            if ($totalNetAmount > 0) {
                $this->merchantTransactionService->credit($merchantId, $totalNetAmount, $orderId);
            }
        }
    }
}