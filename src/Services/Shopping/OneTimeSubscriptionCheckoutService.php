<?php

namespace App\Services\Shopping;

use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Services\Billing\Order\OrderDraftService;
use App\Services\Billing\Payments\PaymentIntentService;
use App\Services\Subscriptions\SubscriptionBatchFactory;
use App\Services\Vouchers\DiscountResolver;

class OneTimeSubscriptionCheckoutService
{
    public function __construct(
        private readonly CartService              $cartService,
        private readonly SubscriptionBatchFactory $subscriptionBatchFactory,
        private readonly OrderDraftService        $orderDraftService,
        private readonly PaymentIntentService     $paymentIntentService,
        private readonly CheckoutResponseBuilder  $responseBuilder,
        private readonly MemberAuthWrapper        $memberAuth,
        private readonly Database         $database,
        private readonly DiscountResolver $discountResolver
    )
    {
    }

    public function processCheckout(array $data, int $siteId): array
    {
        // 1. Validate cart has subscriptions
        $subscriptionItems = $this->getSubscriptionItems();

        if (empty($subscriptionItems)) {
            return [
                'success' => false,
                'message' => 'No subscription in cart'
            ];
        }

        // 2. Validate authentication
        if (!$this->memberAuth->check()) {
            return [
                'success' => false,
                'message' => 'Please login to purchase a subscription',
                'redirect' => '/member/login?redirect=/checkout'
            ];
        }

        $member = $this->memberAuth->getMember();

        // 2.5 RESOLVE DISCOUNTS (ADD THIS SECTION)
        $baseSubtotalCents = $this->calculateBaseSubtotalCents($subscriptionItems);

        $discountContext = new \App\Services\Vouchers\DiscountContext(
            items: $subscriptionItems,
            baseSubtotalCents: $baseSubtotalCents,
            currentSubtotalCents: $baseSubtotalCents,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            isSubscription: true,
            isFirstSubscriptionCycle: true,
            siteId: $siteId
        );

        $resolvedDiscounts = $this->discountResolver->resolve($discountContext);

        // 3. PHASE 1: Database transaction (create subscriptions + order)
        [$order, $subscriptions] = $this->database->transaction(function () use ($subscriptionItems, $data, $member, $siteId, $resolvedDiscounts) {
            // Create subscriptions in pending_payment status
            $subscriptions = $this->subscriptionBatchFactory->createPendingSubscriptions(
                $subscriptionItems,
                $data,
                $member,
                $siteId,
                $resolvedDiscounts  // PASS DISCOUNTS
            );

            // Create order in pending status
            $order = $this->orderDraftService->createPendingOrder(
                $subscriptions,
                $member,
                $siteId,
                $data,
                $resolvedDiscounts  // PASS DISCOUNTS
            );

            return [$order, $subscriptions];
        });

        // 4. PHASE 2: External payment call (OUTSIDE transaction)
        try {
            $paymentResult = $this->paymentIntentService->createForOrder(
                $order,
                $subscriptions,
                $member,
                $siteId
            );

            if (!$paymentResult['success']) {
                // Payment failed - mark subscriptions/order as failed
                $this->handlePaymentFailure($order, $subscriptions);

                return [
                    'success' => false,
                    'message' => 'Payment processing failed'
                ];
            }
        } catch (\Exception $e) {
            // Payment creation failed - mark subscriptions/order as failed
            $this->handlePaymentFailure($order, $subscriptions);

            throw $e;
        }

        // 5. PHASE 3: Update order with payment intent (separate transaction)
        $this->orderDraftService->attachPaymentIntent($order, $paymentResult);

        // 6. Clear cart
        $this->cartService->clear();

        // 7. Build response
        return $this->responseBuilder->buildCheckoutResponse(
            $order,
            $subscriptions,
            $paymentResult
        );
    }

    private function calculateBaseSubtotalCents(array $items): int
    {
        $totalCents = 0;

        foreach ($items as $item) {
            $priceCents = (int)round(($item['base_price'] ?? $item['price']) * 100);
            $quantity = $item['quantity'] ?? 1;
            $totalCents += $priceCents * $quantity;
        }

        return $totalCents;
    }

    private function getSubscriptionItems(): array
    {
        $cartItems = $this->cartService->getItems();
        $subscriptionItems = [];

        foreach ($cartItems as $item) {
            if (!empty($item['subscription_plan_id'])) {
                $subscriptionItems[] = $item;
            }
        }

        return $subscriptionItems;
    }

    private function handlePaymentFailure($order, array $subscriptions): void
    {
        $this->database->transaction(function () use ($order, $subscriptions) {
            // Mark order as payment_failed
            $order->update(['payment_status' => 'failed']);

            // Cancel subscriptions
            foreach ($subscriptions as $subData) {
                $subData['subscription']->update(['status' => 'cancelled']);
            }
        });
    }

}