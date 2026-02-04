<?php

namespace App\Services\Shopping;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use Exception;

class OneTimeSubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly Database                   $database,
        private readonly OrderRepository            $orderRepository,
    )
    {
    }

    public function getOneTimePlans(?int $siteId = null): array
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
                        'original_price' => $tier->original_price,
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
        int    $memberId,
        int    $planId,
        string $deliveryType,
        int    $siteId,
        ?int   $voucherId = null,
        float   $discountAmount = 0,
        ?string $status = null
    ): Subscription
    {
        return $this->database->transaction(function () use (
            $memberId, $planId, $deliveryType, $siteId, $voucherId, $discountAmount
        ) {
            $plan = $this->planRepository->find($planId);

            if (!$plan || !$plan->isOneTime()) {
                throw new Exception('Invalid one-time subscription plan');
            }

            // Validate delivery type
            if ($deliveryType === 'digital' && !$plan->hasDigitalOption()) {
                throw new Exception('Digital delivery not available for this plan');
            }

            if ($deliveryType === 'print' && !$plan->hasPrintOption()) {
                throw new Exception('Print delivery not available for this plan');
            }

            // ALWAYS use a consistent domain date as the base for all calculations
            $startDate = new \DateTime();
            $startDate->setTime(0, 0, 0); // Normalize to midnight for consistency

            $endDate = $this->calculateEndDate($startDate, $plan->billing_period);

            $subscriptionData = [
                'member_id' => $memberId,
                'site_id' => $siteId,
                'plan_id' => $planId,
                'plan_name' => $plan->name,
                'status' => $status ?? SubscriptionStatus::PENDING->value,
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s'),
                'price' => $plan->price - $discountAmount,
                'original_price' => $plan->price,
                'discount_amount' => $discountAmount,
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

    private function calculateEndDate(\DateTime $startDate, string $period): \DateTime
    {
        $endDate = clone $startDate;

        return match ($period) {
            'yearly' => $endDate->modify('+1 year'),
            '2year' => $endDate->modify('+2 years'),
            default => $endDate->modify('+1 year')
        };
    }

    public function activateSubscription(int $subscriptionId, int $orderId): void
    {
        $this->database->transaction(function () use ($subscriptionId, $orderId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'active'
            ]);

            // Link order to subscription
            $this->orderRepository->updateSubscriptionForOrder($orderId, $subscriptionId);
        });
    }

    public function getSubscriptionWithDetails(int $subscriptionId): ?array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            return null;
        }

        $plan = $subscription->plan;
        $order = $subscription->order()->last();

        // Calculate actual payment breakdown
        $subtotal = $subscription->price;
        $discount = $subscription->discount_amount ?? 0;
        $shipping = 0;
        $tax = 0;

        if ($order) {
            $shipping = $order->shipping ?? 0;
            $tax = $order->tax ?? 0;
        } else {
            // Fallback calculation if no order found
            if ($subscription->delivery_type === 'print') {
                $shipping = $subtotal >= 100 ? 0 : 10;
            }
            $taxableAmount = $subtotal - $discount + $shipping;
            $tax = $taxableAmount * 0.1;
        }

        $finalTotal = $subtotal - $discount + $shipping + $tax;

        return [
            'subscription' => $subscription->toArray(),
            'plan' => $plan ? $plan->toArray() : null,
            'order' => $order ? $order->toArray() : null,
            'can_download' => $subscription->hasValidDownload(),
            'download_expires_at' => $subscription->download_expires_at?->format('Y-m-d H:i:s'),
            'payment_breakdown' => [
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping' => $shipping,
                'tax' => $tax,
                'total' => $finalTotal
            ]
        ];
    }
}