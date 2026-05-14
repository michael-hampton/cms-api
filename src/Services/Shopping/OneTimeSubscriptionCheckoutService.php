<?php

namespace App\Services\Shopping;

use App\Actions\Stock\FulfilSubscriptionAction;
use App\DTO\Checkout\DeliveryMethodConfig;
use App\Enums\CartItemType;
use App\Enums\Orders\OrderLineStatus;
use App\Enums\PaymentStatus;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Exceptions\Checkout\CheckoutException;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Models\IssueDelivery;
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
use Exception;

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
        private readonly DiscountResolver         $discountResolver,
        private readonly DeliveryEstimatorInterface $deliveryEstimator,
        private readonly FulfilmentResolver       $fulfilmentResolver,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly CheckoutEligibilityService $eligibilityService,
        private readonly FulfilSubscriptionAction $fulfilSubscriptionAction,
    )
    {
    }

    public function processCheckout(array $data, int $siteId): array
    {
        $subscriptionItems = $this->getSubscriptionItems();

        if (empty($subscriptionItems)) {
            throw new CheckoutException('No subscription in cart');
        }

        if (!$this->memberAuth->check()) {
            throw new CheckoutException(
                'Please login to purchase a subscription',
                ['redirect' => '/member/login?redirect=/checkout']
            );
        }

        $member = $this->memberAuth->getMember();

        // Phase 1: lock plans, validate availability, reserve issue stock,
        // create subscriptions + order — all atomically.
        [$order, $subscriptions, $eligibility, $stockReservations] = $this->database->transaction(
            function () use ($subscriptionItems, $data, $member, $siteId) {

                [$subscriptionItems, $stockReservations] = $this->validateAttachEstimatesAndReserveStock(
                    $subscriptionItems
                );

                $giftFields = [];
                if (!empty($data['is_gift'])) {
                    $giftFields = array_filter([
                        'is_gift' => true,
                        'gift_email' => $data['recipient_email'] ?? null,
                        'gift_first_name' => $data['recipient_first_name'] ?? null,
                        'gift_last_name' => $data['recipient_last_name'] ?? null,
                    ], fn($v) => $v !== null);
                }

                $eligibility = $this->eligibilityService->validate(
                    $member,
                    !empty($giftFields)
                        ? array_map(fn($item) => array_merge($item, $giftFields), $subscriptionItems)
                        : $subscriptionItems
                );
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
                        'order_value' => $baseSubtotalCents,
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

                return [$order, $subscriptions, $eligibility, $stockReservations];
            }
        );

        if (!empty($data['one_time_subscription'])) {
            // Phase 2: external payment call (OUTSIDE transaction)
            try {

                $paymentResult = $this->paymentIntentService->createForOrder(
                    $order,
                    $subscriptions,
                    $member,
                    $siteId
                );

                if (!$paymentResult['success']) {
                    $this->handlePaymentFailure($order, $subscriptions, $stockReservations);
                    throw new CheckoutException('Payment processing failed');
                }
            } catch (CheckoutException $e) {
                throw $e;
            } catch (Exception $e) {
                $this->handlePaymentFailure($order, $subscriptions, $stockReservations);
                throw $e;
            }
        }

        // Phase 3: activate subscriptions + confirm stock.
        //
        // TRIALING subscriptions must NOT be moved to PENDING/SCHEDULED here.
        // createOneTimeSubscription() already set the correct TRIALING status
        // and persisted trial_ends_at.  The TrialConversionService will
        // transition them to ACTIVE after the trial period ends and the first
        // real charge succeeds.
        $today = new DateTimeImmutable('today');

        $this->database->transaction(function () use ($subscriptions, $today, $stockReservations) {
            foreach ($subscriptions as $subData) {
                $subscription = $subData['subscription'];

                // Preserve the TRIALING status set by createOneTimeSubscription.
                if ($subscription->status === SubscriptionStatus::TRIALING->value) {
                    continue;
                }

                $startDate = !empty($subData['selected_start_date'])
                    ? new DateTimeImmutable($subData['selected_start_date'])
                    : null;

                $status = !$startDate || $startDate > $today
                    ? SubscriptionStatus::PENDING
                    : SubscriptionStatus::SCHEDULED; //todo what about trials?

                $subscription->update(['status' => $status->value]);
            }

            foreach ($stockReservations as $reservation) {
                $this->fulfilSubscriptionAction->confirm($reservation['reservationId']);
            }
        });

        if (!empty($paymentResult)) {
            $this->orderDraftService->attachPaymentIntent($order, $paymentResult);
        }

        $this->cartService->clear();

        return $this->responseBuilder->buildCheckoutResponse(
            $order,
            $subscriptions,
            $paymentResult ?? [],
            !empty($eligibility->removed)
        );
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function getSubscriptionItems(): array
    {
        return array_values(array_filter(
            $this->cartService->getItems(),
            fn($item) => !empty($item['subscription_plan_id'])
        ));
    }

    private function validateAttachEstimatesAndReserveStock(array $subscriptionItems): array
    {
        $itemsWithEstimates = [];
        $stockReservations = [];
        $deliveryMethod = DeliveryMethodConfig::default();
        $orderDate = new DateTimeImmutable();

        foreach ($subscriptionItems as $item) {
            $plan = $this->subscriptionPlanRepository->lockForUpdate($item['subscription_plan_id']);

            if (!$plan) {
                throw new CheckoutException("Subscription plan not found");
            }

            $policy = $plan->availabilityPolicy();

            if (!$policy->canPurchase()) {
                throw new CheckoutException(
                    "Subscription not available: " . $policy->getAvailabilityMessage()
                );
            }

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

                    $reservationId = $this->fulfilSubscriptionAction->reserve($nextIssue, $quantity);
                    $stockReservations[] = [
                        'reservationId' => $reservationId,
                        'issue' => $nextIssue,
                        'quantity' => $quantity,
                    ];
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

            $fulfilment = $this->fulfilmentResolver->resolve($plan);
            $estimate = $this->deliveryEstimator->estimate($fulfilment, $deliveryMethod, $orderDate);

            $itemsWithEstimates[] = array_merge($item, [
                'is_pre_release' => $policy->isPreRelease(),
                'release_date' => $plan->release_date?->format('Y-m-d'),
                'order_line_status' => $orderLineStatus,
                'expected_ship_date' => $expectedShipDate?->format('Y-m-d'),
                'is_preorder' => $isPreorder,
                'next_issue_id' => $nextIssue?->id,
                'next_issue_number' => $nextIssue?->issue_number,
                'next_issue_title' => $nextIssue?->issue_title,
                'next_issue_on_sale_date' => $nextIssue?->on_sale_date?->format('Y-m-d'),
                'availability_message' => $policy->getAvailabilityMessage(),
                'estimated_dispatch' => $estimate->dispatchDate?->format('Y-m-d'),
                'estimated_delivery_from' => $estimate->from?->format('Y-m-d'),
                'estimated_delivery_to' => $estimate->to?->format('Y-m-d'),
                'estimated_delivery_formatted' => $estimate->formattedRange(),
                'requires_shipping' => $estimate->requiresShipping,
                'trial_days' => $plan->hasTrial() ? $plan->trial_days : null,
            ]);
        }

        return [$itemsWithEstimates, $stockReservations];
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

    /**
     * @param array<array{reservationId: int, issue: IssueDelivery, quantity: int}> $stockReservations
     */
    private function handlePaymentFailure(object $order, array $subscriptions, array $stockReservations): void
    {
        $this->database->transaction(function () use ($order, $subscriptions, $stockReservations) {
            $order->update(['payment_status' => PaymentStatus::FAILED->value]);

            foreach ($subscriptions as $subData) {
                $subData['subscription']->update(['status' => SubscriptionStatus::CANCELLED->value]);
            }

            foreach ($stockReservations as $reservation) {
                $this->fulfilSubscriptionAction->release($reservation['issue'], $reservation['quantity']);
            }
        });
    }
}