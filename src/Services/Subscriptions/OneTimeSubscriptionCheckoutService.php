<?php

namespace App\Services\Subscriptions;

use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Services\Cms\VoucherService;
use App\Services\Members\OrderCalculationService;
use App\Services\Members\OrderService;
use App\Services\Payment\StripePaymentProcessor;
use App\Services\Shop\CartService;
use App\Services\Shop\ShippingService;

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
            // Validate cart has subscription(s)
            $cartItems = $this->cartService->getItems();
            $subscriptionItems = [];

            foreach ($cartItems as $item) {
                if (!empty($item['subscription_plan_id'])) {
                    $subscriptionItems[] = $item;
                }
            }

            if (empty($subscriptionItems)) {
                return [
                    'success' => false,
                    'message' => 'No subscription in cart'
                ];
            }

            // Check if authenticated
            if (!$this->memberAuth->check()) {
                return [
                    'success' => false,
                    'message' => 'Please login to purchase a subscription',
                    'redirect' => '/member/login?redirect=/checkout'
                ];
            }

            $member = $this->memberAuth->getMember();

            // Process subscriptions
            $result = $this->processMultipleSubscriptions(
                $subscriptionItems,
                $data,
                $member,
                $siteId
            );

            if (!$result['success']) {
                return $result;
            }

            // Clear cart on success
            $this->cartService->clear();

            return $result;
        });
    }

    /**
     * Process multiple subscriptions in a single checkout
     * This method is designed to be moved to a queue/job later if needed
     *
     * @param array $subscriptionItems
     * @param array $data
     * @param $member
     * @param int $siteId
     * @return array
     */
    private function processMultipleSubscriptions(
        array $subscriptionItems,
        array $data,
              $member,
        int   $siteId
    ): array
    {
        $createdSubscriptions = [];
        $orderItems = [];
        $totalSubtotal = 0;
        $totalShipping = 0;
        $totalDiscount = 0;

        try {
            // First pass: Create all subscriptions and calculate totals
            foreach ($subscriptionItems as $item) {
                $options = $item['options'] ?? [];
                $deliveryType = $options['delivery_type'] ?? 'digital';

                // Handle voucher for this specific subscription if applicable
                $voucherId = null;
                $discountAmount = 0;

                if (!empty($data['voucher_code'])) {
                    $voucherValidation = $this->voucherService->validateVoucherForSubscription(
                        $data['voucher_code'],
                        $item['subscription_plan_id'],
                        $member->id
                    );

                    if ($voucherValidation['valid']) {
                        $voucherId = $voucherValidation['voucher_id'];
                        $discountAmount = $voucherValidation['discount'];
                        $totalDiscount += $discountAmount;
                    }
                }

                // Create subscription
                $subscription = $this->subscriptionService->createOneTimeSubscription(
                    $member->id,
                    $item['subscription_plan_id'],
                    $deliveryType,
                    $siteId,
                    $voucherId,
                    $discountAmount
                );

                $createdSubscriptions[] = [
                    'subscription' => $subscription,
                    'item' => $item,
                    'delivery_type' => $deliveryType,
                    'discount' => $discountAmount
                ];

                // Calculate item totals
                $itemSubtotal = $item['price'] - $discountAmount;
                $totalSubtotal += $itemSubtotal;

                if ($deliveryType === 'print') {
                    $shipping = $this->shippingService->calculateShipping($itemSubtotal, $data);
                    $totalShipping += $shipping;
                }
            }

            // Calculate order totals
            $totals = $this->calculationService->calculateOrderTotals([], [
                'subtotal' => $totalSubtotal,
                'shipping' => $totalShipping,
                'discount' => $totalDiscount
            ]);

            // Get subscription IDs
            $subscriptionIds = array_map(fn($s) => $s['subscription']->id, $createdSubscriptions);

            // Create single Stripe payment intent for all subscriptions
            $paymentResult = $this->stripeProcessor->createPaymentIntentWithCustomer([
                'amount' => $totals['total'],
                'currency' => strtolower($createdSubscriptions[0]['subscription']->currency),
                'order_id' => null,
                'subscription_id' => null, // Multiple subscriptions
                'site_id' => $siteId,
                'member' => $member,
                'metadata' => [
                    'subscription_count' => count($createdSubscriptions),
                    'subscription_ids' => implode(',', $subscriptionIds),
                    'member_id' => $member->id,
                    'multiple_subscriptions' => count($subscriptionIds) > 1
                ]
            ]);

            if (!$paymentResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Payment processing failed'
                ];
            }

            // Prepare order items for all subscriptions
            foreach ($createdSubscriptions as $subData) {
                $subscription = $subData['subscription'];
                $item = $subData['item'];
                $deliveryType = $subData['delivery_type'];
                $discount = $subData['discount'];

                $itemSubtotal = $item['price'] - $discount;
                $itemShipping = 0;

                if ($deliveryType === 'print') {
                    $itemShipping = $this->shippingService->calculateShipping($itemSubtotal, $data);
                }

                // Calculate proportional tax
                $itemTax = ($itemSubtotal + $itemShipping) * ($totals['tax'] / ($totalSubtotal + $totalShipping));

                $orderItems[] = [
                    'product_id' => null,
                    'product_name' => $subscription->plan_name . ' (' . ucfirst($deliveryType) . ')',
                    'product_sku' => 'SUB-' . $subscription->id,
                    'quantity' => 1,
                    'unit_price' => $itemSubtotal,
                    'subtotal' => $itemSubtotal,
                    'tax' => $itemTax,
                    'total' => $itemSubtotal + $itemShipping + $itemTax,
                    'metadata' => [
                        'subscription_id' => $subscription->id,
                        'delivery_type' => $deliveryType
                    ]
                ];
            }

            // Create single order for all subscriptions
            $orderData = $this->prepareOrderData($data, $totals, $member, $siteId,
                $this->hasAnyPrintDelivery($createdSubscriptions) ? 'print' : 'digital'
            );

            // Store subscription ID(s) in the order
            // For single subscription, use one_time_subscription_id for backwards compatibility
            // For multiple subscriptions, store all IDs in metadata
            if (count($subscriptionIds) === 1) {
                $orderData['one_time_subscription_id'] = $subscriptionIds[0];
            } else {
                $orderData['one_time_subscription_id'] = $subscriptionIds[0]; // Primary subscription
                $orderData['metadata'] = [
                    'subscription_ids' => $subscriptionIds,
                    'multiple_subscriptions' => true
                ];
            }

            $order = $this->orderService->createOrder($orderData, $orderItems, $siteId);

            // Return appropriate format based on single vs multiple subscriptions
            $result = [
                'success' => true,
                'client_secret' => $paymentResult['client_secret'],
                'payment_intent_id' => $paymentResult['payment_intent_id'],
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'requires_shipping' => $this->hasAnyPrintDelivery($createdSubscriptions)
            ];

            // Add subscription data based on count
            if (count($subscriptionIds) === 1) {
                $result['subscription_id'] = $subscriptionIds[0];
            } else {
                $result['subscription_ids'] = $subscriptionIds;
                $result['multiple_subscriptions'] = true;
            }

            return $result;

        } catch (\Exception $e) {
            // On exception, the database transaction will rollback automatically
            throw $e;
        }
    }

    /**
     * Check if any subscription requires print delivery
     */
    private function hasAnyPrintDelivery(array $createdSubscriptions): bool
    {
        foreach ($createdSubscriptions as $subData) {
            if ($subData['delivery_type'] === 'print') {
                return true;
            }
        }
        return false;
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