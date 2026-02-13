<?php

namespace App\Services\Billing\Order;

use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Order;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\TaxCalculatorService;
use App\Services\Vouchers\ResolvedDiscounts;

class OrderDraftService
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
        private readonly OrderRepository      $orderRepository,
        private readonly TaxCalculatorService $taxCalculatorService,
        private readonly Database             $database
    )
    {
    }

    /**
     * Create order from subscriptions in pending status
     * This happens BEFORE payment
     */
    public function createPendingOrder(
        array              $subscriptionsWithPricing,
        Member             $member,
        int                $siteId,
        array              $checkoutData,
        ?ResolvedDiscounts $resolvedDiscounts = null
    ): Order
    {
        $orderItems = [];
        $totalSubtotalCents = 0;
        $totalShippingCents = 0;
        $totalDiscountCents = 0;

        // Build order items and calculate totals
        foreach ($subscriptionsWithPricing as $subData) {
            $subscription = $subData['subscription'];
            $pricing = $subData['pricing'];

            $totalSubtotalCents += $pricing->subtotalCents;
            $totalShippingCents += $pricing->shippingCents;
            $totalDiscountCents += $pricing->discountCents;

            $meta = $subData['meta'] ?? [];

            $orderItems[] = [
                'product_id' => null,
                'product_name' => $subscription->plan_name . ' (' . ucfirst($pricing->deliveryType) . ')',
                'product_sku' => 'SUB-' . $subscription->id,
                'quantity' => 1,
                'unit_price' => $pricing->getSubtotal(),
                'subtotal' => $pricing->getSubtotal(),
                'tax' => 0, // Will be calculated below
                'total' => $pricing->getSubtotal() + $pricing->getShipping(),
                'preorder_enabled' => $meta['is_preorder'] ?? false,
                'expected_ship_date' => $meta['expected_ship_date'] ?? $meta['estimated_delivery_to'] ?? null,
                'metadata' => array_merge(
                    $meta, // meta overrides defaults if keys collide
                    [
                        'subscription_id' => $subscription->id,
                        'delivery_type' => $pricing->deliveryType
                    ]
                )
            ];
        }

        $country = $checkoutData['country'] ?? 'GB';
        $state = $checkoutData['state'] ?? null;
        $postalCode = $checkoutData['postal_code'] ?? null;

        $taxResult = $this->taxCalculatorService->calculateOrderTax(
            $totalSubtotalCents,
            $totalShippingCents,
            $country,
            $state,
            $postalCode,
            $member
        );

        $totalTaxCents = $taxResult['tax_cents'];

        // Distribute tax proportionally to items using TaxCalculatorService
        if ($totalTaxCents > 0) {
            $orderItems = $this->taxCalculatorService->distributeTaxToItems(
                $orderItems,
                $totalTaxCents
            );

            // Update item totals after tax distribution
            foreach ($orderItems as &$item) {
                $item['total'] = $item['subtotal'] + $item['tax'] +
                    ($item['metadata']['delivery_type'] === 'print'
                        ? ($item['total'] - $item['subtotal'])
                        : 0);
            }
        }

        if (!empty($resolvedDiscounts)) {
            $totalDiscountCents = $resolvedDiscounts->getTotalDiscountCents();
            $totalCents = $resolvedDiscounts->finalSubtotalCents + $totalShippingCents + $totalTaxCents;
        } else {
            $totalCents = $totalSubtotalCents - $totalDiscountCents + $totalShippingCents + $totalTaxCents;
        }

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
            'reward_discount' => $resolvedDiscounts ? $resolvedDiscounts->rewardDiscountCents / 100 : 0,
            'offer_discount' => $resolvedDiscounts ? $resolvedDiscounts->offerDiscountCents / 100 : 0,
            'voucher_discount' => $resolvedDiscounts ? $resolvedDiscounts->voucherDiscountCents / 100 : 0,
            'tiered_discount' => $resolvedDiscounts ? $resolvedDiscounts->tieredDiscountCents / 100 : 0,
            'merchant_funded' => $resolvedDiscounts->merchantFundedCents / 100,
            'platform_funded' => $resolvedDiscounts->platformFundedCents / 100,
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