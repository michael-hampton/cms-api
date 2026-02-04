<?php

namespace App\Services\Billing\Order;

use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Order;
use App\Repositories\Billing\OrderRepository;

class OrderDraftService
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
        private readonly OrderRepository      $orderRepository,
        private readonly Database             $database
    )
    {
    }

    /**
     * Create order from subscriptions in pending status
     * This happens BEFORE payment
     */
    public function createPendingOrder(
        array  $subscriptionsWithPricing,
        Member $member,
        int    $siteId,
        array  $checkoutData
    ): Order
    {
        $orderItems = [];
        $totalSubtotalCents = 0;
        $totalShippingCents = 0;
        $totalDiscountCents = 0;
        $totalTaxCents = 0;

        // Build order items and calculate totals
        foreach ($subscriptionsWithPricing as $subData) {
            $subscription = $subData['subscription'];
            $pricing = $subData['pricing'];

            $totalSubtotalCents += $pricing->subtotalCents;
            $totalShippingCents += $pricing->shippingCents;
            $totalDiscountCents += $pricing->discountCents;

            $orderItems[] = [
                'product_id' => null,
                'product_name' => $subscription->plan_name . ' (' . ucfirst($pricing->deliveryType) . ')',
                'product_sku' => 'SUB-' . $subscription->id,
                'quantity' => 1,
                'unit_price' => $pricing->getSubtotal(),
                'subtotal' => $pricing->getSubtotal(),
                'tax' => 0, // Will be calculated below
                'total' => $pricing->getSubtotal() + $pricing->getShipping(),
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'delivery_type' => $pricing->deliveryType
                ]
            ];
        }

        // Calculate order-level tax (FIXED: division by zero protection)
        $taxBase = $totalSubtotalCents + $totalShippingCents;
        $taxRatio = 0.10; // This should come from tax service
        $totalTaxCents = $taxBase > 0 ? (int)round($taxBase * $taxRatio) : 0;

        // Distribute tax proportionally to items
        if ($totalTaxCents > 0 && $taxBase > 0) {
            foreach ($orderItems as &$item) {
                $itemBaseCents = (int)round(($item['subtotal'] + ($item['metadata']['delivery_type'] === 'print' ? $item['total'] - $item['subtotal'] : 0)) * 100);
                $itemTaxCents = (int)round($itemBaseCents * ($totalTaxCents / $taxBase));
                $item['tax'] = $itemTaxCents / 100;
                $item['total'] = $item['subtotal'] + ($item['total'] - $item['subtotal']) + $item['tax'];
            }
        }

        $totalCents = $totalSubtotalCents - $totalDiscountCents + $totalShippingCents + $totalTaxCents;

        // Prepare order data
        $orderData = [
            'user_id' => $member->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'subtotal' => $totalSubtotalCents / 100,
            'tax' => $totalTaxCents / 100,
            'shipping' => $totalShippingCents / 100,
            'discount' => $totalDiscountCents / 100,
            'total' => $totalCents / 100,
            'currency' => 'USD', // Should be from config or plan
        ];

        // Add subscription IDs to order
        $subscriptionIds = array_map(fn($s) => $s['subscription']->id, $subscriptionsWithPricing);
        if (count($subscriptionIds) === 1) {
            $orderData['one_time_subscription_id'] = $subscriptionIds[0];
        } else {
            $orderData['one_time_subscription_id'] = $subscriptionIds[0];
            $orderData['metadata'] = [
                'subscription_ids' => $subscriptionIds,
                'multiple_subscriptions' => true
            ];
        }


        // Add shipping address if any subscription requires it
        $requiresShipping = $this->hasAnyPrintDelivery($subscriptionsWithPricing);
        if ($requiresShipping) {
            if (!empty($checkoutData['saved_address'])) {
                $orderData['shipping_address_id'] = $checkoutData['saved_address'];
            } else {
                $orderData['shipping_address'] = $subscriptionsWithPricing[0]['pricing']->shippingAddressSnapshot;
                $orderData['billing_address'] = $orderData['shipping_address'];
            }
        }

        return $this->orderCreationService->create($orderData, $orderItems, $siteId);
    }

    private function hasAnyPrintDelivery(array $subscriptionsWithPricing): bool
    {
        foreach ($subscriptionsWithPricing as $subData) {
            if ($subData['pricing']->deliveryType === 'print') {
                return true;
            }
        }
        return false;
    }

    /**
     * Attach payment intent ID to order AFTER Stripe call succeeds
     */
    public function attachPaymentIntent(Order $order, array $paymentResult): void
    {
        $this->database->transaction(function () use ($order, $paymentResult) {
            $this->orderRepository->update($order->id, [
                'payment_intent_id' => $paymentResult['payment_intent_id'],
                'stripe_customer_id' => $paymentResult['customer_id'] ?? null,
            ]);
        });
    }
}