<?php

namespace App\Services\Shop;

use App\Framework\Authorization\MemberAuthWrapper;
use App\Services\Cms\VoucherService;
use App\Services\Members\OrderCalculationService;
use App\Services\Members\OrderService;
use App\Services\Payment\StripePaymentProcessor;
use Exception;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly VoucherService $voucherService,
        private readonly ShippingService $shippingService,
        private readonly MemberAuthWrapper $memberAuthWrapper,
        private readonly OrderCalculationService $calculationService,
        private readonly StripePaymentProcessor  $stripeProcessor // ADD THIS
    ) {}


    public function processCheckout(array $data, int $siteId): array
    {
        // Validate required fields
        $validation = $this->validateCheckoutData($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }

        // Get cart items
        $cartItems = $this->cartService->getItems();
        if (empty($cartItems)) {
            return [
                'success' => false,
                'message' => 'Cart is empty'
            ];
        }

        // Calculate totals
        $totals = $this->calculateTotals($cartItems, $data);

        // Create Stripe payment intent
        $paymentResult = $this->stripeProcessor->createPaymentIntent([
            'amount' => $totals['total'],
            'currency' => 'usd',
            'site_id' => $siteId,
            'metadata' => [
                'checkout_type' => 'regular'
            ]
        ]);

        if (!$paymentResult['success']) {
            return [
                'success' => false,
                'message' => $paymentResult['message'] ?? 'Failed to create payment intent'
            ];
        }

        // Prepare order data
        $orderData = $this->prepareOrderData($data, $totals, $siteId);

        try {
            // Prepare order items
            $items = $this->prepareOrderItems($cartItems);

            // Create order
            $order = $this->orderService->createOrder($orderData, $items, $siteId);

            // Apply voucher if provided
            if (!empty($totals['voucher_id']) && $totals['discount'] > 0) {
                $userId = $this->memberAuthWrapper->check() ? $this->memberAuthWrapper->member()->id : null;
                $this->voucherService->applyVoucher(
                    $totals['voucher_id'],
                    $userId,
                    $totals['discount'],
                    $order->id
                );
            }

            // Return payment intent details for client confirmation
            return [
                'success' => true,
                'message' => 'Order placed successfully',
                'client_secret' => $paymentResult['client_secret'],
                'payment_intent_id' => $paymentResult['payment_intent_id'],
                'order_id' => $order->order_number,
                'order_internal_id' => $order->id,
                'total' => $totals['total']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ];
        }
    }

    private function validateCheckoutData(array $data): array
    {
        $required = ['first_name', 'last_name', 'email', 'phone'];

        if (empty($data['saved_address'])) {
            $required = array_merge($required, ['address', 'city', 'postal_code', 'country']);
        }

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'valid' => false,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'
                ];
            }
        }

        return ['valid' => true];
    }

    private function calculateTotals(array $cartItems, array $data): array
    {
        $subtotal = $this->cartService->getTotal();

        $shipping = $this->shippingService->calculateShipping($subtotal, $data);

        $discount = 0;
        $voucherCode = null;
        $voucherId = null;

        if (!empty($data['voucher_code']) && !empty($data['voucher_id']) && !empty($data['discount_amount'])) {
            $discount = (float) $data['discount_amount'];
            $voucherCode = $data['voucher_code'];
            $voucherId = (int) $data['voucher_id'];
        }

        // Use shared calculation service
        $calculatedTotals = $this->calculationService->calculateOrderTotals(
            [], // No items needed, we have subtotal
            [
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'discount' => $discount
            ]
        );

        return array_merge($calculatedTotals, [
            'voucher_code' => $voucherCode,
            'voucher_id' => $voucherId
        ]);
    }

    private function prepareOrderData(array $data, array $totals, int $siteId): array
    {
        $orderData = [
            'customer_name' => $data['first_name'] . ' ' . $data['last_name'],
            'customer_email' => $data['email'],
            'customer_phone' => $data['phone'],
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => $data['payment_method'] ?? 'card',
            'subtotal' => $totals['subtotal'],
            'tax' => $totals['tax'],
            'shipping' => $totals['shipping'],
            'discount' => $totals['discount'],
            'total' => $totals['total'],
            'currency' => 'USD',
            'voucher_code' => $totals['voucher_code'],
        ];

        if (!empty($data['saved_address'])) {
            $orderData['shipping_address_id'] = $data['saved_address'];
        } else {
            $shippingAddress = [
                'address_line_1' => $data['address'],
                'address_line_2' => $data['address2'] ?? '',
                'city' => $data['city'],
                'state' => $data['state'] ?? '',
                'postcode' => $data['postal_code'],
                'country' => $data['country']
            ];

            $orderData['shipping_address'] = $shippingAddress;
            $orderData['billing_address'] = $shippingAddress;
        }

        if ($this->memberAuthWrapper->check()) {
            $orderData['user_id'] = $this->memberAuthWrapper->member()->id;
        }

        return $orderData;
    }

    private function prepareOrderItems(array $cartItems): array
    {
        $items = [];
        foreach ($cartItems as $cartItem) {
            $items[] = [
                'product_id' => $cartItem['product_id'],
                'product_name' => $cartItem['product_name'],
                'product_sku' => $cartItem['product_sku'] ?? '',
                'quantity' => $cartItem['quantity'],
                'unit_price' => $cartItem['price'],
                'subtotal' => $cartItem['subtotal'],
                'tax' => 0,
                'total' => $cartItem['subtotal']
            ];
        }
        return $items;
    }

    public function confirmRegularCheckoutPayment(string $paymentIntentId, int $orderId): array
    {
        try {
            $confirmResult = $this->stripeProcessor->confirmPaymentIntent($paymentIntentId);

            if (!$confirmResult['success'] || $confirmResult['status'] !== 'succeeded') {
                return [
                    'success' => false,
                    'message' => 'Payment confirmation failed'
                ];
            }

            // Update order status
            $this->orderService->updateOrderStatus($orderId, 'completed', 'paid');

            // Clear cart after successful payment
            $this->cartService->clear();

            return [
                'success' => true,
                'message' => 'Order completed successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment confirmation error: ' . $e->getMessage()
            ];
        }
    }
}