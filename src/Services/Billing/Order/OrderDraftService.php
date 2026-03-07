<?php

namespace App\Services\Billing\Order;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\Subscriptions\SubscriptionType;
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
     * Create order from subscriptions in pending status.
     * This happens BEFORE payment.
     */
    public function createPendingOrder(
        array              $subscriptionsWithPricing,
        Member             $member,
        int                $siteId,
        array              $checkoutData,
        ?ResolvedDiscounts $resolvedDiscounts = null,
        bool               $isFreeOrder = false
    ): Order
    {
        $orderItems = [];
        $totalSubtotalCents = 0;
        $totalShippingCents = 0;
        $totalDiscountCents = 0;
        // FIX: accumulate totalCents inside the loop instead of reading $pricing
        // after the loop ends (which only held the last iteration's value).
        $totalCents = 0;

        foreach ($subscriptionsWithPricing as $subData) {
            $subscription = $subData['subscription'];
            /** @var SubscriptionPricing $pricing */
            $pricing = $subData['pricing'];

            $totalSubtotalCents += $pricing->subtotalCents;
            $totalShippingCents += $pricing->shippingCents;
            $totalDiscountCents += $pricing->discountCents;
            $totalCents += $pricing->totalCents;

            $meta = $subData['meta'] ?? [];

            $orderItems[] = [
                'product_id' => null,
                'product_name' => $subscription->plan_name . ' (' . ucfirst($pricing->deliveryType) . ')',
                'product_sku' => 'SUB-' . $subscription->id,
                'quantity' => 1,
                'unit_price' => $pricing->getSubtotal(),
                'subtotal' => $pricing->getSubtotal(),
                'tax' => 0,
                'total' => $pricing->getSubtotal() + $pricing->getShipping(),
                'preorder_enabled' => $meta['is_preorder'] ?? false,
                'expected_ship_date' => $meta['expected_ship_date'] ?? $meta['estimated_delivery_to'] ?? null,
                'metadata' => array_merge(
                    $meta,
                    [
                        'subscription_id' => $subscription->id,
                        'delivery_type' => $pricing->deliveryType,
                    ]
                ),
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
        $totalTaxCents = $taxResult->taxCents;

        if ($totalTaxCents > 0) {
            $orderItems = $this->taxCalculatorService->distributeTaxToItems(
                $orderItems,
                $totalTaxCents
            );

            foreach ($orderItems as &$item) {
                $item['total'] = $item['subtotal'] + $item['tax'] +
                    ($item['metadata']['delivery_type'] === SubscriptionType::PRINTED->value
                        ? ($item['total'] - $item['subtotal'])
                        : 0);
            }
            unset($item);
        }

        // Tax is not included in individual pricing->totalCents, add it once here
        $totalCents += $totalTaxCents;

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
            'currency' => 'USD',
            'reward_discount' => $resolvedDiscounts ? $resolvedDiscounts->rewardDiscountCents / 100 : 0,
            'offer_discount' => $resolvedDiscounts ? $resolvedDiscounts->offerDiscountCents / 100 : 0,
            'voucher_discount' => $resolvedDiscounts ? $resolvedDiscounts->voucherDiscountCents / 100 : 0,
            'tiered_discount' => $resolvedDiscounts ? $resolvedDiscounts->tieredDiscountCents / 100 : 0,
            // FIX: these were accessed unconditionally on the original, causing a fatal
            // TypeError when $resolvedDiscounts is null.
            'merchant_funded' => $resolvedDiscounts ? $resolvedDiscounts->merchantFundedCents / 100 : 0,
            'platform_funded' => $resolvedDiscounts ? $resolvedDiscounts->platformFundedCents / 100 : 0,
        ];

        if ($isFreeOrder) {
            $orderData['subtotal'] = 0;
            $orderData['shipping'] = 0;
            $orderData['tax'] = 0;
            $orderData['total'] = 0;
            $orderData['discount'] = 0;
            $orderData['offer_discount'] = 0;
            $orderData['voucher_discount'] = 0;
            $orderData['reward_discount'] = 0;
            $orderData['tiered_discount'] = 0;
        }

        $subscriptionIds = array_map(fn($s) => $s['subscription']->id, $subscriptionsWithPricing);

        if (count($subscriptionIds) === 1) {
            $orderData['one_time_subscription_id'] = $subscriptionIds[0];
        } else {
            $orderData['one_time_subscription_id'] = $subscriptionIds[0];
            $orderData['metadata'] = [
                'subscription_ids' => $subscriptionIds,
                'multiple_subscriptions' => true,
            ];
        }

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
            if ($subData['pricing']->deliveryType === SubscriptionType::PRINTED->value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Attach payment intent ID to order AFTER Stripe call succeeds.
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