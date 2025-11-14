<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\OrderService;
use Exception;

class CloneOrder
{
    private Database $database;

    public function __construct(
        private readonly OrderRepository         $orderRepository,
        private readonly OrderService $orderService,
        ?Database                                $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(int $orderId): Order
    {
        return $this->database->transaction(function () use ($orderId) {

            $originalOrder = $this->orderRepository->getOrderById($orderId);

            if (!$originalOrder) {
                throw new Exception("Order not found");
            }

            $data = [
                'user_id' => $originalOrder->user_id,
                'status' => 'pending',
                'subtotal' => $originalOrder->subtotal,
                'tax' => $originalOrder->tax,
                'shipping' => $originalOrder->shipping,
                'discount' => $originalOrder->discount,
                'total' => $originalOrder->total,
                'currency' => $originalOrder->currency,
                'shipping_address' => $originalOrder->shipping_address,
                'billing_address' => $originalOrder->billing_address,
                'payment_method' => $originalOrder->payment_method,
                'payment_status' => 'unpaid'
            ];

            // Handle address duplication
            if ($originalOrder->shipping_address_id) {
                // If original order has linked address, use the same address
                $data['shipping_address_id'] = $originalOrder->shipping_address_id;
            } elseif ($originalOrder->shipping_address) {
                // If original order has JSON address, copy it
                $data['shipping_address'] = $originalOrder->shipping_address;
            }

            if ($originalOrder->billing_address_id) {
                $data['billing_address_id'] = $originalOrder->billing_address_id;
            } elseif ($originalOrder->billing_address) {
                $data['billing_address'] = $originalOrder->billing_address;
            }

            $items = [];
            foreach ($originalOrder->items as $item) {
                $items[] = [
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                    'tax' => $item->tax,
                    'total' => $item->total,
                    'metadata' => $item->metadata
                ];
            }

            $newOrder = $this->orderService->createOrder($data, $items, $originalOrder->site_id);

            // Add clone history
            $originalOrder->addCloneRecord('cloned_to', $newOrder->id, null);
            $newOrder->addCloneRecord('cloned_from', $originalOrder->id, null);

            return $newOrder;
        });
    }
}