<?php

namespace App\Services;

use App\Framework\Database\Database;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Mail\OrderConfirmation;
use App\Models\Model;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Repositories\AddressRepository;
use App\Repositories\MemberRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use Exception;

class OrderService
{
    private Database $database;

    public function __construct(
        private readonly OrderRepository         $orderRepository,
        private readonly OrderItemRepository     $orderItemRepository,
        private readonly MemberRepository        $memberRepository,
        private readonly AddressRepository       $addressRepository,
        private readonly OrderCalculationService $calculationService,
        private readonly OrderHistoryService     $historyService,
        private readonly MailManager $mailManager,
        private readonly PaymentService $paymentService,
        ?Database                                $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function getAllOrders(): Collection
    {
        return Order::with(['items', 'user'])->orderBy('created_at', 'desc')->get();
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

    public function createOrder(array $data, array $items, int $siteId): Order
    {
        return $this->database->transaction(function () use ($data, $items, $siteId) {
            // Handle user creation if user_id is not provided
            if (empty($data['user_id']) && !empty($data['customer_email'])) {
                $member = $this->createOrGetMember($data, $siteId);
                $data['user_id'] = !empty($member) ? $member->id : null;
            } elseif (!empty($data['user_id'])) {
                $member = $this->memberRepository->find($data['user_id']);
            }

            // Handle shipping address
            if (isset($data['shipping_address_id'])) {
                // Address ID provided, verify it belongs to the member
                if ($member) {
                    $address = $this->addressRepository->find($data['shipping_address_id']);
                    if (!$address || $address->member_id !== $member->id) {
                        throw new Exception("Invalid shipping address");
                    }
                }
            } elseif (isset($data['shipping_address']) && is_array($data['shipping_address']) && !empty(array_filter($data['shipping_address']))) {
                // Address data provided, create new address if member exists
                if (isset($member)) {
                    $addressData = $data['shipping_address'];
                    $addressData['type'] = 'shipping';
                    $addressData['label'] = 'Order Address';

                    $newAddress = $this->addressRepository->createAddressForMember($member->id, $addressData, $siteId);
                    $data['shipping_address_id'] = $newAddress->id;
                    unset($data['shipping_address']); // Remove JSON data
                } else {
                    $data['shipping_address'] = json_encode($data['shipping_address']);
                }
                // For guest orders, keep shipping_address as JSON
            }

            // Handle billing address
            if (isset($data['billing_address_id'])) {
                if ($member) {
                    $address = $this->addressRepository->find($data['billing_address_id']);
                    if (!$address || $address->member_id !== $member->id) {
                        throw new Exception("Invalid billing address");
                    }
                }
            } elseif (isset($data['billing_address']) && is_array($data['billing_address']) && !empty(array_filter($data['billing_address']))) {
                if (isset($member)) {
                    $addressData = $data['billing_address'];
                    $addressData['type'] = 'billing';
                    $addressData['label'] = 'Order Billing Address';

                    $newAddress = $this->addressRepository->createAddressForMember($member->id, $addressData, $siteId);
                    $data['billing_address_id'] = $newAddress->id;
                    unset($data['billing_address']);
                }
            }

            if (!empty($data['billing_address']) && is_array($data['billing_address'])) {
                $data['billing_address'] = json_encode($data['billing_address']);
            }

            if (!empty($data['shipping_address']) && is_array($data['shipping_address'])) {
                $data['shipping_address'] = json_encode($data['shipping_address']);
            }

            // Remove customer fields from order data as they're not part of orders table
            unset($data['customer_name'], $data['customer_email'], $data['customer_phone']);

            // Generate order number if not provided
            if (empty($data['order_number'])) {
                $data['order_number'] = $this->generateOrderNumber();
            }

            // Calculate totals from items
            $calculatedTotals = $this->calculateTotals($items, $data);
            $data = array_merge($data, $calculatedTotals);

            $data['site_id'] = $siteId;
            $data['status'] = $data['status'] ?? 'pending';
            $data['payment_status'] = $data['payment_status'] ?? 'unpaid';

            // Create order
            $order = $this->orderRepository->create($data);

            $this->historyService->logCreated($order->id, $data, $data['user_id'] ?? null);

            // Create order items
            foreach ($items as $item) {
                $this->createOrderItem($order->id, $item);
            }

            $customerEmail = $order->user?->email ?: ($data['customer_email'] ?? null);

            if ($customerEmail) {
                try {
                    $this->mailManager->to($customerEmail)->send(new OrderConfirmation($order));
                } catch (\Exception $e) {
                    Logger::error("Failed to send order confirmation email", [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Reload with items
            return $this->getOrderById($order->id);
        });
    }

    private function createOrGetMember(array $data, int $siteId): ?Model
    {
        $email = $data['customer_email'];

        // Check if member already exists
        $existingMember = $this->memberRepository->findByEmail($email);

        if ($existingMember) {
            return $existingMember;
        }

        // Parse customer name into first_name and last_name
        $nameParts = $this->parseCustomerName($data['customer_name'] ?? '');

        if (empty($nameParts)) {
            return null;
        }

        // Create new member
        $memberData = [
            'site_id' => $siteId,
            'email' => $email,
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), // Random password
            'is_active' => true,
        ];

        return $this->memberRepository->create($memberData);
    }

    private function parseCustomerName(string $fullName): array
    {
        $fullName = trim($fullName);

        if (empty($fullName)) {
            return [];
        }

        $parts = explode(' ', $fullName, 2);

        return [
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? ''
        ];
    }

    public function updateOrder(int $id, array $data, ?int $siteId = null, ?int $userId = null): Order
    {
        return $this->database->transaction(function () use ($id, $data, $siteId, $userId) {
            $siteId = $siteId ?? SiteContext::getId();
            $order = $this->orderRepository->find($id);

            if (!$order) {
                throw new Exception("Order not found");
            }

            $oldData = $order->toArray();

            if (isset($data['status']) && $data['status'] !== $order->status) {
                $this->validateStatusTransition($order, $data['status']);
            }

            $member = null;
            if ($order->user_id) {
                $member = $this->memberRepository->find($order->user_id);
            }

            // Handle shipping address update
            if (isset($data['shipping_address_id'])) {
                // Verify address belongs to member
                if ($member) {
                    $address = $this->addressRepository->find($data['shipping_address_id']);
                    if (!$address || $address->member_id !== $member->id) {
                        throw new Exception("Invalid shipping address");
                    }
                }
                // Clear JSON address if using address_id
                $data['shipping_address'] = null;
            } elseif (isset($data['shipping_address']) && is_array($data['shipping_address']) && !empty(array_filter($data['shipping_address']))) {
                // Address data provided, create new address if member exists
                if ($member) {
                    $addressData = $data['shipping_address'];
                    $addressData['type'] = 'shipping';
                    $addressData['label'] = 'Order Address (Updated)';

                    $newAddress = $this->addressRepository->createAddressForMember($member->id, $addressData, $siteId);
                    $data['shipping_address_id'] = $newAddress->id;
                    unset($data['shipping_address']); // Remove JSON data
                }
                // For guest orders, keep shipping_address as JSON
            }

            // Handle billing address update
            if (isset($data['billing_address_id'])) {
                if ($member) {
                    $address = $this->addressRepository->find($data['billing_address_id']);
                    if (!$address || $address->member_id !== $member->id) {
                        throw new Exception("Invalid billing address");
                    }
                }
                $data['billing_address'] = null;
            } elseif (isset($data['billing_address']) && is_array($data['billing_address']) && !empty(array_filter($data['billing_address']))) {
                if ($member) {
                    $addressData = $data['billing_address'];
                    $addressData['type'] = 'billing';
                    $addressData['label'] = 'Order Billing Address (Updated)';

                    $newAddress = $this->addressRepository->createAddressForMember($member->id, $addressData, $siteId);
                    $data['billing_address_id'] = $newAddress->id;
                    unset($data['billing_address']);
                }
            }

            // Handle status transitions
            if (isset($data['status']) && $data['status'] !== $order->status) {
                $this->handleStatusChange($order, $data['status']);
            }

            $updatedOrder = $this->orderRepository->update($id, $data);

            if (!$updatedOrder) {
                throw new Exception("Failed to update order");
            }

            $this->historyService->logUpdated($id, $oldData, $data, $userId);

            return $this->getOrderById($id);
        });
    }

    public function updateOrderItems(int $orderId, array $items, ?int $userId = null): Order
    {
        return $this->database->transaction(function () use ($orderId, $items, $userId) {
            $order = $this->orderRepository->find($orderId);

            if (!$order) {
                throw new Exception("Order not found");
            }

            // Delete existing items
            $this->orderItemRepository->deleteByOrderId($orderId);

            // Create new items
            foreach ($items as $item) {
                $this->createOrderItem($orderId, $item);
            }

            // Recalculate order totals
            $calculatedTotals = $this->calculateTotals($items, $order->toArray());
            $this->orderRepository->update($orderId, $calculatedTotals);

            $this->historyService->logItemsUpdated($orderId, $userId);

            return $this->getOrderById($orderId);
        });
    }

    public function cancelOrder(int $orderId, ?string $reason = null, ?int $userId = null): Order
    {
        return $this->database->transaction(function () use ($orderId, $reason, $userId) {
            $order = $this->orderRepository->find($orderId);

            if (!$order) {
                throw new Exception("Order not found");
            }

            if (!$order->canBeCancelled()) {
                throw new Exception("Order cannot be cancelled in its current status");
            }

            $updateData = [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s')
            ];

            if ($reason) {
                $updateData['admin_notes'] = ($order->admin_notes ? $order->admin_notes . "\n\n" : '')
                    . "Cancellation reason: " . $reason;
            }

            $updatedOrder = $this->updateOrder($orderId, $updateData);

            $this->historyService->logCancelled($orderId, $userId, $reason);

            return $updatedOrder;
        });
    }

    public function completeOrder(int $orderId): Order
    {
        return $this->updateOrder($orderId, [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function refundOrder(int $orderId, ?string $reason = null): Order
    {
        return $this->database->transaction(function () use ($orderId, $reason) {
            $order = $this->orderRepository->find($orderId);

            if (!$order) {
                throw new Exception("Order not found");
            }

            $updateData = [
                'status' => 'refunded',
                'payment_status' => 'refunded'
            ];

            if ($reason) {
                $updateData['admin_notes'] = ($order->admin_notes ? $order->admin_notes . "\n\n" : '')
                    . "Refund reason: " . $reason;
            }

            return $this->updateOrder($orderId, $updateData);
        });
    }

    public function deleteOrder(int $orderId): bool
    {
        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            throw new Exception('Order not found');
        }

        return $this->database->transaction(function () use ($order) {
            // Items will be deleted via cascade
            return $order->delete();
        });
    }



    private function createOrderItem(int $orderId, array $itemData): OrderItem
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

    private function calculateTotals(array $items, array $orderData): array
    {
        return $this->calculationService->calculateOrderTotals($items, $orderData);
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

    private function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = time();
        $random = rand(1000, 9999);

        $orderNumber = $prefix . '-' . $timestamp . '-' . $random;

        // Ensure uniqueness
        if ($this->orderRepository->findByOrderNumber($orderNumber)) {
            return $this->generateOrderNumber();
        }

        return $orderNumber;
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
        return $this->orderRepository->getTotalRevenue($startDate, $endDate);
    }



    private function validateStatusTransition(Order $order, string $newStatus): void
    {
        if (!$order->canTransitionTo($newStatus)) {
            throw new \Exception("Cannot transition from {$order->status} to {$newStatus}");
        }
    }

    public function createOrderWithPayment(array $data, array $items, int $siteId, array $paymentData): array
    {
        return $this->database->transaction(function () use ($data, $items, $siteId, $paymentData) {
            // Create the order
            $order = $this->createOrder($data, $items, $siteId);

            // Create payment if payment method provided
            if (!empty($paymentData['payment_method'])) {
                $payment = $this->paymentService->createPayment(
                    $order->id,
                    $paymentData,
                    $siteId
                );

                return [
                    'order' => $order,
                    'payment' => $payment
                ];
            }

            return [
                'order' => $order,
                'payment' => null
            ];
        });
    }

    public function processOrderPayment(int $orderId, array $paymentData, int $siteId): Payment
    {
        return $this->database->transaction(function () use ($orderId, $paymentData, $siteId) {
            $order = $this->orderRepository->find($orderId);
            if (!$order) {
                throw new Exception('Order not found');
            }

            // Create payment for existing order
            $payment = $this->paymentService->createPayment($orderId, $paymentData, $siteId);

            // If payment method doesn't require processing, mark as completed immediately
            $paymentMethod = $this->paymentService->paymentMethodRepository->findByCode($paymentData['payment_method']);
            if ($paymentMethod && !$paymentMethod->requiresProcessing()) {
                $payment = $this->paymentService->completePayment($payment->id);
            }

            return $payment;
        });
    }
}