<?php

namespace App\Services\Shopping;

use App\DTO\Checkout\DeliveryMethodConfig;
use App\Enums\CartItemType;
use App\Enums\Orders\OrderLineStatus;
use App\Enums\PaymentStatus;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Exceptions\Checkout\CheckoutException;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Order\OrderDraftService;
use App\Services\Billing\Payments\PaymentIntentService;
use App\Services\Shipping\DeliveryEstimatorInterface;
use App\Services\Shipping\FulfilmentResolver;
use App\Services\Subscriptions\SubscriptionBatchFactory;
use App\Services\Vouchers\DiscountContext\DiscountContext;
use App\Services\Vouchers\DiscountContext\VoucherContext;
use App\Services\Vouchers\DiscountResolver;
use DateTimeImmutable;

class OneTimeSubscriptionCheckoutService
{
    public function __construct(
        private readonly CartService              $cartService,
        private readonly SubscriptionBatchFactory $subscriptionBatchFactory,
        private readonly OrderDraftService        $orderDraftService,
        private readonly PaymentIntentService     $paymentIntentService,
        private readonly CheckoutResponseBuilder  $responseBuilder,
        private readonly MemberAuthWrapper        $memberAuth,
        private readonly Database                 $database,
        private readonly DiscountResolver           $discountResolver,
        private readonly DeliveryEstimatorInterface $deliveryEstimator,
        private readonly FulfilmentResolver         $fulfilmentResolver,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly CheckoutEligibilityService $eligibilityService,
    )
    {
    }

    public function processCheckout(array $data, int $siteId): array
    {
        // 1. Validate cart has subscriptions
        $subscriptionItems = $this->getSubscriptionItems();

        if (empty($subscriptionItems)) {
            throw new CheckoutException('No subscription in cart');
        }

        // 2. Validate authentication
        if (!$this->memberAuth->check()) {
            throw new CheckoutException(
                'Please login to purchase a subscription',
                ['redirect' => '/member/login?redirect=/checkout']
            );
        }

        $member = $this->memberAuth->getMember();

        // 3. PHASE 1: Lock plans, validate availability, create subscriptions + order atomically.
        //    lockForUpdate() must live inside the transaction to hold the row lock through the write.
        [$order, $subscriptions, $eligibility] = $this->database->transaction(
            function () use ($subscriptionItems, $data, $member, $siteId) {

                // Validates availability, locks plans, and attaches delivery estimates.
                // Must run inside the transaction so the FOR UPDATE lock is held through the write.
                $subscriptionItems = $this->validateAndAttachEstimates($subscriptionItems);

                $eligibility = $this->eligibilityService->validate($member, $subscriptionItems);
                $subscriptionItems = $eligibility->valid;

                if (empty($subscriptionItems)) {
                    throw new CheckoutException('All items were invalid and removed from the cart.');
                }

                $baseSubtotalCents = $this->calculateBaseSubtotalCents($subscriptionItems);

                $paidItems = array_values(array_filter(
                    $subscriptionItems,
                    fn($item) => ($item['options']['type'] ?? '') !== CartItemType::FREE_GIFT->value
                        && ($item['base_price'] ?? $item['price'] ?? 0) > 0
                ));

                $freeGiftItems = array_values(array_filter(
                    $subscriptionItems,
                    fn($item) => ($item['options']['type'] ?? '') === CartItemType::FREE_GIFT->value
                        || ($item['base_price'] ?? $item['price'] ?? 0) <= 0
                ));

                $discountContext = new DiscountContext(
                    items: $paidItems,
                    baseSubtotalCents: $baseSubtotalCents,
                    currentSubtotalCents: $baseSubtotalCents,
                    currentOfferDiscountCents: 0,
                    appliedDiscounts: [],
                    member: $member,
                    isSubscription: true,
                    isFirstSubscriptionCycle: true,
                    siteId: $siteId,
                    voucherContext: !empty($data['voucher_code']) ? new VoucherContext([
                        'voucher_code' => $data['voucher_code'],
                        'voucher_id' => $data['voucher_id'],
                        'applies_to' => 'subscription_first_cycle',
                        'subscription_plan_id' => $subscriptionItems[0]['subscription_plan_id'] ?? null,
                        'pricing_tier_id' => $subscriptionItems[0]['options']['pricing_tier_id'] ?? null,
                        'delivery_type' => $subscriptionItems[0]['options']['delivery_type'] ?? null,
                        'order_value' => $baseSubtotalCents
                    ]) : null,
                    freeGiftItems: $freeGiftItems
                );

                $resolvedDiscounts = $this->discountResolver->resolve($discountContext);

                $subscriptions = $this->subscriptionBatchFactory->createPendingSubscriptions(
                    $subscriptionItems,
                    $data,
                    $member,
                    $siteId,
                    $resolvedDiscounts
                );

                $allFreeGifts = !array_filter(
                    $subscriptionItems,
                    fn($item) => ($item['options']['type'] ?? '') !== CartItemType::FREE_GIFT->value
                        && ($item['base_price'] ?? $item['price'] ?? 0) > 0
                );

                $order = $this->orderDraftService->createPendingOrder(
                    $subscriptions,
                    $member,
                    $siteId,
                    $data,
                    $resolvedDiscounts,
                    (bool)$allFreeGifts
                );

                return [$order, $subscriptions, $eligibility];
            }
        );

        // 4. PHASE 2: External payment call (OUTSIDE transaction)
        try {
            $paymentResult = $this->paymentIntentService->createForOrder(
                $order,
                $subscriptions,
                $member,
                $siteId
            );

            if (!$paymentResult['success']) {
                $this->handlePaymentFailure($order, $subscriptions);
                throw new CheckoutException('Payment processing failed');
            }
        } catch (CheckoutException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handlePaymentFailure($order, $subscriptions);
            throw $e;
        }

        // 5. PHASE 3: Attach payment intent to order
        $this->orderDraftService->attachPaymentIntent($order, $paymentResult);

        // 6. PHASE 4: Activate subscriptions now that payment is confirmed.
        //    If the member selected a future start date, the subscription is SCHEDULED
        //    rather than ACTIVE — a scheduler will transition it on the start date.
        //    Same-day start (start_date === today) is treated as immediately active.
        $today = new DateTimeImmutable('today');

        $this->database->transaction(function () use ($subscriptions, $today) {
            foreach ($subscriptions as $subData) {
                $startDate = !empty($subData['selected_start_date'])
                    ? new DateTimeImmutable($subData['selected_start_date'])
                    : null;

                $status = ($startDate && $startDate > $today)
                    ? SubscriptionStatus::PENDING
                    : SubscriptionStatus::SCHEDULED;

                $subData['subscription']->update(['status' => $status->value]);
            }
        });

        // 7. Clear cart
        $this->cartService->clear();

        // 8. Build response
        return $this->responseBuilder->buildCheckoutResponse(
            $order,
            $subscriptions,
            $paymentResult,
            !empty($eligibility->removed)
        );
    }

    private function calculateBaseSubtotalCents(array $items): int
    {
        $totalCents = 0;

        foreach ($items as $item) {
            if (($item['options']['type'] ?? '') === CartItemType::FREE_GIFT->value) {
                continue;
            }
            $priceCents = (int)round(($item['base_price'] ?? $item['price']) * 100);
            $quantity = $item['quantity'] ?? 1;
            $totalCents += $priceCents * $quantity;
        }

        return $totalCents;
    }

    private function getSubscriptionItems(): array
    {
        return array_values(array_filter(
            $this->cartService->getItems(),
            fn($item) => !empty($item['subscription_plan_id'])
        ));
    }

    private function handlePaymentFailure($order, array $subscriptions): void
    {
        $this->database->transaction(function () use ($order, $subscriptions) {
            $order->update(['payment_status' => PaymentStatus::FAILED->value]);

            foreach ($subscriptions as $subData) {
                $subData['subscription']->update(['status' => SubscriptionStatus::CANCELLED->value]);
            }
        });
    }

    private function validateAndAttachEstimates(array $subscriptionItems): array
    {
        $itemsWithEstimates = [];
        $deliveryMethod = DeliveryMethodConfig::default();
        $orderDate = new DateTimeImmutable();

        foreach ($subscriptionItems as $item) {
            $plan = $this->subscriptionPlanRepository->lockForUpdate($item['subscription_plan_id']);

            if (!$plan) {
                throw new CheckoutException("Subscription plan not found");
            }

            // STEP 1: Check plan availability via policy
            $policy = $plan->availabilityPolicy();

            if (!$policy->canPurchase()) {
                throw new CheckoutException(
                    "Subscription not available: " . $policy->getAvailabilityMessage()
                );
            }

            // STEP 2: For PRINT subscriptions, validate next issue
            $orderLineStatus = null;
            $expectedShipDate = null;
            $isPreorder = false;
            $nextIssue = null;

            if ($plan->print_shipping_required) {
                $nextIssue = $plan->getNextIssue();

                if (!$nextIssue) {
                    throw new CheckoutException("No issues scheduled for {$plan->name}");
                }

                $issuePolicy = $nextIssue->availabilityPolicy();

                if (!$issuePolicy->canPurchase()) {
                    throw new CheckoutException(
                        "Next issue not available: " . $issuePolicy->getAvailabilityMessage()
                    );
                }

                $quantity = $item['quantity'] ?? 1;

                if ($nextIssue->stock_quantity >= $quantity) {
                    $orderLineStatus = OrderLineStatus::READY_TO_SHIP->value;
                } elseif ($issuePolicy->isPreOrder()) {
                    $orderLineStatus = OrderLineStatus::PENDING_PREORDER->value;
                    $expectedShipDate = $issuePolicy->getExpectedShipDate();
                    $isPreorder = true;

                    if (!$expectedShipDate) {
                        throw new CheckoutException('Pre-order requires expected ship date');
                    }
                } else {
                    throw new CheckoutException(
                        "Issue #{$nextIssue->issue_number} out of stock. " .
                        "Available: {$nextIssue->stock_quantity}, Requested: {$quantity}"
                    );
                }
            }

            // STEP 3: Calculate delivery estimates
            $fulfilment = $this->fulfilmentResolver->resolve($plan);

            $estimate = $this->deliveryEstimator->estimate(
                $fulfilment,
                $deliveryMethod,
                $orderDate
            );

            // STEP 4: Attach all metadata to cart item.
            // This data flows through to SubscriptionBatchFactory and OrderDraftService.
            $itemsWithEstimates[] = array_merge($item, [
                // Plan-level pre-release (content not ready)
                'is_pre_release' => $policy->isPreRelease(),
                'release_date' => $plan->release_date?->format('Y-m-d'),

                // Issue-level pre-order (stock not ready) - ONLY for print
                'order_line_status' => $orderLineStatus,
                'expected_ship_date' => $expectedShipDate?->format('Y-m-d'),
                'is_preorder' => $isPreorder,

                // Next issue info (for display and order item metadata)
                'next_issue_id' => $nextIssue?->id,
                'next_issue_number' => $nextIssue?->issue_number,
                'next_issue_title' => $nextIssue?->issue_title,
                'next_issue_on_sale_date' => $nextIssue?->on_sale_date?->format('Y-m-d'),

                // Availability message for UI
                'availability_message' => $policy->getAvailabilityMessage(),

                // Delivery estimates for UI
                'estimated_dispatch' => $estimate->dispatchDate?->format('Y-m-d'),
                'estimated_delivery_from' => $estimate->from?->format('Y-m-d'),
                'estimated_delivery_to' => $estimate->to?->format('Y-m-d'),
                'estimated_delivery_formatted' => $estimate->formattedRange(),
                'requires_shipping' => $estimate->requiresShipping,
            ]);
        }

        return $itemsWithEstimates;
    }
}