<?php

namespace App\Services\Shopping;

use App\Framework\Authorization\MemberAuthWrapper;
use App\Repositories\Billing\ShipmentRepository;
use App\Services\Billing\CheckoutSplittingService;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\OrderService;
use App\Services\Billing\PaymentAllocationService;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Vouchers\VoucherService;
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
        private readonly StripePaymentProcessor   $stripeProcessor,
        private readonly CheckoutSplittingService $splittingService,
        private readonly PaymentAllocationService $allocationService,
        private readonly MerchantShippingService  $merchantShippingService,
        private readonly ShipmentRepository       $shipmentRepository,
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
            'voucher_code' => $totals['voucher_code'] ?? null,
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

    /**
     * Multi-merchant checkout flow.
     *
     * 1. Validate & get cart
     * 2. Split items by merchant
     * 3. Calculate per-merchant shipping
     * 4. Allocate payment across groups
     * 5. Create one Stripe PaymentIntent per merchant order (logical; single capture)
     * 6. Persist each merchant order + shipment
     * 7. Return unified response with all order numbers
     *
     * Subscription items must be excluded before calling this method.
     */
    public function processMultiMerchantCheckout(array $data, int $siteId): array
    {
        // --- Validation ---
        $validation = $this->validateCheckoutData($data);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        $cartItems = $this->cartService->getItems();
        if (empty($cartItems)) {
            return ['success' => false, 'message' => 'Cart is empty'];
        }

        // --- Split ---
        $groups = $this->splittingService->splitByMerchant($cartItems);

        if (empty($groups)) {
            return ['success' => false, 'message' => 'No items to process'];
        }

        // --- Shipping ---
        $country = $data['country'] ?? 'US';
        $shippingPerGroup = $this->merchantShippingService->calculatePerGroup($groups, $country);

        // --- Totals at checkout level (for allocation math) ---
        $subtotal = $this->cartService->getTotal();
        $totalShipping = array_sum($shippingPerGroup);

        $discount = 0.0;
        $voucherCode = null;
        $voucherId = null;
        if (!empty($data['voucher_code']) && !empty($data['voucher_id']) && !empty($data['discount_amount'])) {
            $discount = (float)$data['discount_amount'];
            $voucherCode = $data['voucher_code'];
            $voucherId = (int)$data['voucher_id'];
        }

        $checkoutTotals = $this->calculationService->calculateOrderTotals(
            [],
            ['subtotal' => $subtotal, 'shipping' => $totalShipping, 'discount' => $discount]
        );

        // --- Allocate ---
        $allocations = $this->allocationService->allocate($groups, $checkoutTotals, $shippingPerGroup);

        // --- Stripe: one PaymentIntent per merchant order ---
        $stripeContexts = [];
        foreach ($groups as $key => $group) {
            // Zero-total groups (e.g. fully discounted, free system buckets) have no
            // Stripe object. The order is still persisted internally.
            if (!($allocations[$key]['stripe_eligible'] ?? true)) {
                continue;
            }

            $piResult = $this->stripeProcessor->createPaymentIntent([
                'amount' => $allocations[$key]['total'],
                'currency' => strtolower($data['currency'] ?? 'usd'),
                'site_id' => $siteId,
                'metadata' => [
                    'checkout_type' => 'multi_merchant',
                    'merchant_id' => $group['merchant_id'],
                    'stripe_group_key' => $group['stripe_group_key'],
                ],
            ]);

            if (!$piResult['success']) {
                return [
                    'success' => false,
                    'message' => $piResult['message'] ?? 'Failed to create payment intent',
                ];
            }

            $stripeContexts[$key] = $piResult;
        }

        // --- Persist orders + shipments ---
        $checkoutId = 'chk-' . uniqid('', true);
        $orderNumbers = [];

        try {
            foreach ($groups as $key => $group) {
                $items = $this->prepareOrderItems($group['items']);

                $orderData = $this->prepareOrderData($data, $allocations[$key], $siteId);
                $orderData['voucher_code'] = $voucherCode;
                $orderData['checkout_id'] = $checkoutId;
                // Store Stripe context and checkout linkage in metadata-style fields
                $orderData['metadata'] = [
                    'checkout_id' => $checkoutId,
                    'merchant_id' => $group['merchant_id'],
                    'stripe_payment_intent_id' => $stripeContexts[$key]['payment_intent_id'],
                    'stripe_client_secret' => $stripeContexts[$key]['client_secret'],
                ];

                $order = $this->orderService->createMerchantOrder($orderData, $items, $siteId, $group['merchant_id']);
                $orderNumbers[] = $order->order_number;

                // --- Shipment ---
                $this->shipmentRepository->create([
                    'order_id' => $order->id,
                    'checkout_id' => $checkoutId,
                    'merchant_id' => $group['merchant_id'],
                    'shipping_cost' => $allocations[$key]['shipping'],
                    'country' => $country,
                    'status' => 'pending',
                    'metadata' => [
                        'consolidation_enabled' => $this->merchantShippingService->isConsolidationEnabled(),
                    ],
                    'site_id' => $siteId,
                ]);
            }

            // Apply voucher once at checkout level if applicable
            if (!empty($voucherId) && $discount > 0) {
                $userId = $this->memberAuthWrapper->check() ? $this->memberAuthWrapper->member()->id : null;
                // Use the first order's id as the anchor for the voucher usage record
                $this->voucherService->applyVoucher($voucherId, $userId, $discount, null);
            }

            // Clear cart
            //$this->cartService->clear();

            return [
                'success' => true,
                'message' => 'Multi-merchant checkout completed',
                'checkout_id' => $checkoutId,
                'order_numbers' => $orderNumbers,
                'total' => $checkoutTotals['total'],
                'stripe_contexts' => array_map(fn($ctx) => [
                    'payment_intent_id' => $ctx['payment_intent_id'],
                    'client_secret' => $ctx['client_secret'],
                ], $stripeContexts),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create orders: ' . $e->getMessage(),
            ];
        }
    }
}