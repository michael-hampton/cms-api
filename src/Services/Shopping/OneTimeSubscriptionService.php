<?php

namespace App\Services\Shopping;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Framework\Database\Database;
use App\Models\Model;
use App\Models\Subscription;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;
use App\Services\Subscriptions\Calculators\SubscriptionPricingCalculator;
use App\Services\Subscriptions\Validators\OneTimePlanValidator;
use App\Services\ValueObjects\Money;

class OneTimeSubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly Database                   $database,
        private readonly OrderRepository            $orderRepository,
        private readonly OneTimePlanValidator          $validator,
        private readonly SubscriptionDateCalculator    $dateCalculator,
        private readonly SubscriptionPricingCalculator $pricingCalculator,
    )
    {
    }

    public function getOneTimePlansCatalog(?int $siteId = null): array
    {
        $plans = $this->planRepository->getActivePlans($siteId)
            ->filter(fn($plan) => $plan->isOneTime());

        return $plans->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'billing_period' => $plan->billing_period,
                'features' => $plan->features,
                'delivery_options' => $plan->getDeliveryOptions(),
                'digital_only' => $plan->hasDigitalOption() && !$plan->hasPrintOption(),
                'print_only' => $plan->hasPrintOption() && !$plan->hasDigitalOption(),
                'both_options' => $plan->hasDigitalOption() && $plan->hasPrintOption(),
                'pricing_tiers' => $plan->pricingTiers->map(function ($tier) {
                    return [
                        'id' => $tier->id,
                        'duration_months' => $tier->duration_months,
                        'issue_count' => $tier->issue_count,
                        'price' => $tier->price,
                        'digital_price' => $tier->digital_price,
                        'digital_sale_price' => $tier->digital_sale_price,
                        'sale_price' => $tier->sale_price,
                        'discount_percentage' => $tier->discount_percentage,
                        'label' => $tier->label,
                        'period_description' => $tier->period_description,
                        'is_default' => $tier->is_default,
                        'has_discount' => $tier->hasDiscount(),
                        'savings_text' => $tier->getSavingsText(),
                    ];
                })->toArray()
            ];
        })->toArray();
    }

    public function createOneTimeSubscription(
        int                 $memberId,
        int                 $planId,
        string $deliveryType,
        int                 $siteId,
        ?int                $voucherId = null,
        int                 $discountAmountCents = 0,
        ?SubscriptionStatus $status = null,
        ?string $selectedStartDate = null,
        ?string $accessStartsAt = null,
        ?string $firstShipmentAt = null
    ): Subscription
    {
        return $this->database->transaction(function () use (
            $memberId,
            $planId,
            $deliveryType,
            $siteId,
            $voucherId,
            $discountAmountCents,
            $status,
            $selectedStartDate
        ) {
            $plan = $this->planRepository->find($planId);

            // Validate plan and delivery type
            $this->validator->validatePlanForSubscription($plan, $deliveryType);
            $billingPeriod = $this->validator->validateBillingPeriod($plan->billing_period);

            // Calculate dates
            $startDate = $this->dateCalculator->normalizeStartDate($selectedStartDate);
            $endDate = $this->dateCalculator->calculateEndDate($startDate, $billingPeriod);

            // Calculate pricing
            $basePrice = Money::fromDecimal($plan->price, $plan->currency);
            $discount = Money::fromCents($discountAmountCents, $plan->currency);

            $this->pricingCalculator->validateDiscount($basePrice, $discount);
            $finalPrice = $this->pricingCalculator->calculateFinalPrice($basePrice, $discount);

            $subscriptionData = [
                'member_id' => $memberId,
                'site_id' => $siteId,
                'plan_id' => $planId,
                'plan_name' => $plan->name,
                'status' => ($status ?? SubscriptionStatus::PENDING)->value,
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s'),
                'price' => $finalPrice->toDecimal(),
                'original_price' => $basePrice->toDecimal(),
                'discount_amount' => $discount->toDecimal(),
                'voucher_id' => $voucherId,
                'currency' => $plan->currency,
                'auto_renew' => false,
                'delivery_type' => $deliveryType,
            ];

            $subscription = $this->subscriptionRepository->create($subscriptionData);

            // Generate download URL if digital
            if ($deliveryType === 'digital') {
                $subscription->generateDownloadUrl('');
            }

            return $subscription;
        });
    }

    public function activateSubscription(int $subscriptionId, int $orderId): void
    {
        $this->database->transaction(function () use ($subscriptionId, $orderId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new SubscriptionNotFoundException('Subscription not found');
            }

            // Enforce state transition: only PENDING can become ACTIVE
            if ($subscription->status !== SubscriptionStatus::PENDING->value) {
                throw new \InvalidArgumentException(
                    "Cannot activate subscription with status: {$subscription->status}"
                );
            }

            $this->subscriptionRepository->update($subscriptionId, [
                'status' => SubscriptionStatus::ACTIVE->value
            ]);

            // Link order to subscription
            $this->orderRepository->updateSubscriptionForOrder($orderId, $subscriptionId);
        });
    }

    public function getSubscriptionSummary(int $subscriptionId): ?array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            return null;
        }

        $plan = $subscription->plan;
        $order = $subscription->order()->last();

        // Use order data as source of truth for financials
        if ($order) {
            $subtotalCents = (int)round($subscription->price * 100);
            $discountCents = (int)round(($subscription->discount_amount ?? 0) * 100);
            $shippingCents = (int)round($order->shipping * 100);
            $taxCents = (int)round($order->tax * 100);
            $totalCents = $subtotalCents - $discountCents + $shippingCents + $taxCents;

            $breakdown = [
                'subtotal_cents' => $subtotalCents,
                'subtotal' => $subscription->price,
                'discount_cents' => $discountCents,
                'discount' => $subscription->discount_amount ?? 0,
                'shipping_cents' => $shippingCents,
                'shipping' => $order->shipping,
                'tax_cents' => $taxCents,
                'tax' => $order->tax,
                'total_cents' => $totalCents,
                'total' => $totalCents / 100,
                'is_estimate' => false,
            ];
        } else {
            // Estimated calculation when no order exists
            $subtotalCents = (int)round($subscription->price * 100);
            $discountCents = (int)round(($subscription->discount_amount ?? 0) * 100);

            $shippingCents = 0;
            if ($subscription->delivery_type === 'print') {
                $shippingCents = $subscription->price >= 100 ? 0 : 1000; // $10.00
            }

            $taxableAmountCents = $subtotalCents - $discountCents + $shippingCents;
            $taxCents = (int)round($taxableAmountCents * 0.1);

            $totalCents = $subtotalCents - $discountCents + $shippingCents + $taxCents;

            $breakdown = [
                'subtotal_cents' => $subtotalCents,
                'subtotal' => $subtotalCents / 100,
                'discount_cents' => $discountCents,
                'discount' => $discountCents / 100,
                'shipping_cents' => $shippingCents,
                'shipping' => $shippingCents / 100,
                'tax_cents' => $taxCents,
                'tax' => $taxCents / 100,
                'total_cents' => $totalCents,
                'total' => $totalCents / 100,
                'is_estimate' => true,
            ];
        }

        return [
            'subscription' => $subscription->toArray(),
            'plan' => $plan ? $plan->toArray() : null,
            'order' => $order ? $order->toArray() : null,
            'can_download' => $subscription->hasValidDownload(),
            'download_expires_at' => $subscription->download_expires_at?->format('Y-m-d H:i:s'),
            'payment_breakdown' => $breakdown,
        ];
    }

    public function getPlanWithPricingTiers(int $planId): ?Model
    {
        return $this->planRepository->findWithPricingTiers($planId);
    }
}