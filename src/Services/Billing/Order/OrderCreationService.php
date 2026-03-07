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
            $member = $this->memberResolver->resolve($data, $siteId);
            if ($member) {
                $data['user_id'] = $member->id;
            }

            $this->addressResolver->resolveAddresses($data, $member, $siteId);

            // Capture BEFORE prepareOrderData strips it
            $customerEmail = $data['customer_email'] ?? null;

            $data = $this->prepareOrderData($data, $items, $siteId, null, $discountBreakdown);

            $order = $this->orderRepository->create($data);

            $this->createOrderItems($order->id, $items);
            $this->creditMerchantsForOrder($order->id);

            $this->historyService->logCreated($order->id, $data, $data['user_id'] ?? null);

            event(new OrderCreatedEvent($order, $customerEmail));

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
            $member = $this->memberResolver->resolve($data, $siteId);
            if ($member) {
                $data['user_id'] = $member->id;
            }

            $this->addressResolver->resolveAddresses($data, $member, $siteId);

            // FIX: was undefined — must be captured before prepareOrderData strips customer fields
            $customerEmail = $data['customer_email'] ?? null;

            $data = $this->prepareOrderData($data, $items, $siteId, $merchantId);

            $order = $this->orderRepository->create($data);

            $this->createOrderItems($order->id, $items);

            if ($merchantId) {
                $orderItems = $this->orderItemRepository->getByOrderId($order->id);
                $totalNetAmount = 0;

                foreach ($orderItems as $orderItem) {
                    $totalNetAmount += $orderItem->net_amount ?? 0;
                }

                if ($totalNetAmount > 0) {
                    $this->merchantTransactionService->credit($merchantId, $totalNetAmount, $order->id);
                }
            }

            $this->historyService->logCreated($order->id, $data, $data['user_id'] ?? null);

            event(new OrderCreatedEvent($order, $customerEmail));

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
        unset($data['customer_name'], $data['customer_email'], $data['customer_phone']);

        if (empty($data['order_number'])) {
            $data['order_number'] = $this->numberGenerator->generate();
        }

        if (!isset($data['total'])) {
            $calculatedTotals = $this->calculationService->calculateOrderTotals($items, $data);
            $data = array_merge($data, $calculatedTotals);
        }

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

            if (!isset($itemData['subtotal'])) {
                $itemData['subtotal'] = $itemData['unit_price'] * $itemData['quantity'];
            }

            if (!isset($itemData['total'])) {
                $itemData['total'] = $itemData['subtotal'] + ($itemData['tax'] ?? 0);
            }

            $itemData['status'] = $item['order_line_status'] ?? OrderLineStatus::READY_TO_SHIP->value;
            $itemData['expected_ship_date'] = $item['expected_ship_date'] ?? null;
            $itemData['quantity_allocated'] = $item['quantity_allocated'] ?? $itemData['quantity'];

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
                    $itemData['commission_rate'] = 0.0000;
                    $itemData['commission_amount'] = 0.00;
                    $itemData['net_amount'] = $itemData['subtotal'];
                }
            } else {
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

    private function creditMerchantsForOrder(int $orderId): void
    {
        $orderItems = $this->orderItemRepository->getByOrderId($orderId);
        $merchantTotals = [];

        foreach ($orderItems as $item) {
            if (!$item->merchant_id) {
                continue;
            }

            $merchantTotals[$item->merchant_id] = ($merchantTotals[$item->merchant_id] ?? 0)
                + $item->net_amount;
        }

        foreach ($merchantTotals as $merchantId => $totalNetAmount) {
            if ($totalNetAmount > 0) {
                $this->merchantTransactionService->credit($merchantId, $totalNetAmount, $orderId);
            }
        }
    }
}