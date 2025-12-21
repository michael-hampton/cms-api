<?php

namespace App\Services;

use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Services\Payment\StripePaymentProcessor;
use Exception;

class OneTimeSubscriptionCheckoutService
{
    public function __construct(
        private readonly CartService                $cartService,
        private readonly OneTimeSubscriptionService $subscriptionService,
        private readonly OrderService               $orderService,
        private readonly VoucherService             $voucherService,
        private readonly ShippingService            $shippingService,
        private readonly StripePaymentProcessor     $stripeProcessor,
        private readonly MemberAuthWrapper          $memberAuth,
        private readonly OrderCalculationService    $calculationService,
        private readonly Database                   $database
    )
    {
    }

    public function processCheckout(array $data, int $siteId): array
    {
        return $this->database->transaction(function () use ($data, $siteId) {
            // Validate cart has subscription
            $cartItems = $this->cartService->getItems();
            $subscriptionItem = null;

            foreach ($cartItems as $item) {
                if (!empty($item['subscription_plan_id'])) {
                    $subscriptionItem = $item;
                    break;
                }
            }

            if (!$subscriptionItem) {
                return [
                    'success' => false,
                    'message' => 'No subscription in cart'
                ];
            }

            // Get delivery type from cart options
            $options = $subscriptionItem['options'];
            $deliveryType = $options['delivery_type'] ?? 'digital';

            // Check if authenticated
            if (!$this->memberAuth->check()) {
                return [
                    'success' => false,
                    'message' => 'Please login to purchase a subscription',
                    'redirect' => '/member/login?redirect=/checkout'
                ];
            }

            $member = $this->memberAuth->member();

            // Handle voucher
            $voucherId = null;
            $discountAmount = 0;

            if (!empty($data['voucher_code'])) {
                $voucherValidation = $this->voucherService->validateVoucherForSubscription(
                    $data['voucher_code'],
                    $subscriptionItem['subscription_plan_id'],
                    $member->id
                );

                if ($voucherValidation['valid']) {
                    $voucherId = $voucherValidation['voucher_id'];
                    $discountAmount = $voucherValidation['discount'];
                }
            }

            // Create subscription
            $subscription = $this->subscriptionService->createOneTimeSubscription(
                $member->id,
                $subscriptionItem['subscription_plan_id'],
                $deliveryType,
                $siteId,
                $voucherId,
                $discountAmount
            );

            // Calculate totals
            $subtotal = $subscriptionItem['price'] - $discountAmount;
            $shipping = 0;

            if ($deliveryType === 'print') {
                $shipping = $this->shippingService->calculateShipping($subtotal, $data);
            }

            $totals = $this->calculationService->calculateOrderTotals([], [
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'discount' => $discountAmount
            ]);

            // Create Stripe payment intent
            $paymentResult = $this->stripeProcessor->createPaymentIntent([
                'amount' => $subtotal,
                'currency' => strtolower($subscription->currency),
                'order_id' => null,
                'subscription_id' => $subscription->id,
                'site_id' => $siteId,
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'delivery_type' => $deliveryType,
                    'member_id' => $member->id,
                ]
            ]);

            if (!$paymentResult['success']) {
                echo '<pre>';
                print_r($paymentResult);
                die;
                die('no');
                throw new Exception($paymentResult['message'] ?? 'Failed to create payment intent');
            }

            // Create order
            $orderData = $this->prepareOrderData($data, $totals, $member, $siteId, $deliveryType);
            $orderData['one_time_subscription_id'] = $subscription->id;

            $orderItems = [[
                'product_id' => null,
                'product_name' => $subscription->plan_name . ' (' . ucfirst($deliveryType) . ')',
                'product_sku' => 'SUB-' . $subscription->id,
                'quantity' => 1,
                'unit_price' => $subscriptionItem['price'] - $discountAmount,
                'subtotal' => $subtotal,
                'tax' => $totals['tax'],
                'total' => $totals['total']
            ]];

            $order = $this->orderService->createOrder($orderData, $orderItems, $siteId);

            // Clear cart
            $this->cartService->clear();

            return [
                'success' => true,
                'client_secret' => $paymentResult['client_secret'],
                'payment_intent_id' => $paymentResult['payment_intent_id'],
                'subscription_id' => $subscription->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'requires_shipping' => $deliveryType === 'print'
            ];
        });
    }

    private function prepareOrderData(
        array  $data,
        array  $totals,
               $member,
        int    $siteId,
        string $deliveryType
    ): array
    {
        $orderData = [
            'user_id' => $member->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'subtotal' => $totals['subtotal'],
            'tax' => $totals['tax'],
            'shipping' => $totals['shipping'],
            'discount' => $totals['discount'],
            'total' => $totals['total'],
            'currency' => 'USD',
        ];

        if ($deliveryType === 'print') {
            if (!empty($data['saved_address'])) {
                $orderData['shipping_address_id'] = $data['saved_address'];
            } else {
                $orderData['shipping_address'] = [
                    'address_line_1' => $data['address'],
                    'address_line_2' => $data['address2'] ?? '',
                    'city' => $data['city'],
                    'state' => $data['state'] ?? '',
                    'postcode' => $data['postal_code'],
                    'country' => $data['country']
                ];
                $orderData['billing_address'] = $orderData['shipping_address'];
            }
        }

        return $orderData;
    }
}