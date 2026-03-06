<?php

namespace App\Services\Subscriptions;

use App\Actions\Subscriptions\CreatePlanAction;
use App\DTO\Subscriptions\SubscriptionPlanData;
use App\Exceptions\Subscriptions\AlreadySubscribedException;
use App\Exceptions\Subscriptions\PlanHasActiveSubscriptionsException;
use App\Exceptions\Subscriptions\PlanNotFoundException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Vouchers\VoucherService;

class SubscriptionPlanService
{
    public function __construct(
        private readonly SubscriptionPlanRepository     $planRepository,
        private readonly SubscriptionRepository         $subscriptionRepository,
        private readonly VoucherService                 $voucherService,
        private readonly SubscriptionEligibilityService $eligibilityService,
        private readonly CreatePlanAction               $createPlanAction,
        private readonly SubscriptionPlanPricingService $pricingService,
        private readonly Database $database,
    )
    {
    }

    public function getActivePlansForSite(int $siteId): Collection
    {
        return $this->planRepository->getActivePlans($siteId);
    }

    public function getFeaturedPlans(int $siteId): Collection
    {
        return $this->planRepository->getFeaturedPlans($siteId);
    }

    public function getPlanBySlug(string $slug, int $siteId): ?SubscriptionPlan
    {
        return $this->planRepository->findBySlug($slug, $siteId);
    }

    /**
     * Create a plan and its corresponding Stripe Product.
     *
     * If price data is provided (price + currency + interval), a pricing tier
     * is also created via SubscriptionPlanPricingService, which delegates to
     * AddPlanPriceAction and creates the corresponding Stripe Price.
     *
     * Plans without pricing tiers (no price data supplied) only get a Stripe
     * Product — a Stripe Price is added later when a pricing tier is attached.
     */
    public function createPlan(array $data, int $siteId): SubscriptionPlan
    {
        return $this->database->transaction(function () use ($data, $siteId) {
            $planDataDto = SubscriptionPlanData::fromArray($data, $siteId);
            $plan = $this->createPlanAction->execute($planDataDto->toArray());

            if ($this->hasPriceData($data)) {
                $pricingData = $this->preparePricingData($data);
                $this->pricingService->createPricingTier($plan->id, $pricingData);
            }

            if (isset($data['region_set_ids'])) {
                $ids = $this->normaliseRegionSetIds($data['region_set_ids']);
                $plan->regionSets(true)->sync($ids);
            }

            return $plan;
        });
    }

    /**
     * Determine whether the caller supplied enough data to create a pricing tier.
     */
    private function hasPriceData(array $data): bool
    {
        return isset($data['price'])
            && isset($data['currency'])
            && isset($data['billing_period']);
    }

    /**
     * Map plan-level fields into the shape expected by SubscriptionPlanPricingService.
     */
    private function preparePricingData(array $data): array
    {
        $durationMonths = $data['duration_months'] ?? $this->billingPeriodToDurationMonths($data['billing_period']);

        return [
            'price' => $data['price'],
            'currency' => $data['currency'],
            'interval' => $this->billingPeriodToInterval($data['billing_period']),
            'duration_months' => $durationMonths,
            'issue_count' => $data['issue_count'] ?? 1,
            'is_default' => true,
            'sort_order' => $data['sort_order'] ?? 1,
            'label' => $data['name'],
            'period_description' => $this->billingPeriodToInterval($data['billing_period'])
        ];
    }

    private function billingPeriodToDurationMonths(string $billingPeriod): int
    {
        return match ($billingPeriod) {
            'weekly' => 1,
            'monthly' => 1,
            'quarterly' => 3,
            'yearly', 'annual' => 12,
            default => 1,
        };
    }

    private function billingPeriodToInterval(string $billingPeriod): string
    {
        return match ($billingPeriod) {
            'weekly' => 'week',
            'monthly' => 'month',
            'yearly', 'annual' => 'year',
            default => 'month',
        };
    }

    public function updatePlan(int $planId, array $data, ?int $siteId = null): ?SubscriptionPlan
    {
        $existingPlan = $this->planRepository->find($planId);

        if (!$existingPlan) {
            throw new PlanNotFoundException("Plan with ID {$planId} not found");
        }

        if ($siteId !== null && $existingPlan->site_id !== $siteId) {
            throw new \InvalidArgumentException('Cannot update plan from different site');
        }

        if (isset($data['slug']) && $data['slug'] !== $existingPlan->slug) {
            $activeCount = $this->planRepository->getSubscriberCount($planId);
            if ($activeCount > 0) {
                throw new PlanHasActiveSubscriptionsException(
                    'Cannot change slug for plan with active subscriptions'
                );
            }
        }

        $planDataDto = SubscriptionPlanData::fromArray($data);
        $plan = $this->planRepository->update($planId, $planDataDto->toArray());

        if ($plan && isset($data['region_set_ids'])) {
            $ids = $this->normaliseRegionSetIds($data['region_set_ids']);
            $plan->regionSets(true)->sync($ids);
        }

        return $plan;
    }

    public function deletePlan(int $planId): bool
    {
        $activeCount = $this->planRepository->getSubscriberCount($planId);

        if ($activeCount > 0) {
            throw new PlanHasActiveSubscriptionsException(
                "Cannot delete plan with {$activeCount} active subscriptions"
            );
        }

        return $this->planRepository->delete($planId);
    }

    public function subscribeMemberToPlan(
        int $memberId,
        int $planId,
        int $siteId,
        array $paymentData = []
    ): Subscription
    {
        $eligibility = $this->eligibilityService->canMemberSubscribe($memberId, $planId, $siteId);

        if (!$eligibility['can_subscribe']) {
            throw new AlreadySubscribedException($eligibility['reason']);
        }

        return $this->subscriptionRepository->createSubscription(
            $memberId,
            $planId,
            $siteId,
            $paymentData
        );
    }

    public function canMemberSubscribe(int $memberId, int $planId, int $siteId): array
    {
        return $this->eligibilityService->canMemberSubscribe($memberId, $planId, $siteId);
    }

    public function getPlanWithStats(int $planId): array
    {
        $plan = $this->planRepository->find($planId);

        if (!$plan) {
            throw new PlanNotFoundException("Plan with ID {$planId} not found");
        }

        $subscriberCount = $this->planRepository->getSubscriberCount($planId);

        return [
            'plan' => $plan,
            'subscriber_count' => $subscriberCount,
            'revenue' => $this->calculatePlanRevenue($plan, $subscriberCount),
        ];
    }

    private function calculatePlanRevenue(SubscriptionPlan $plan, int $subscriberCount): float
    {
        return $subscriberCount * $plan->price;
    }

    public function getAllPlansWithStats(int $siteId): array
    {
        $plans = $this->planRepository->getAllForSite($siteId);

        $planIds = $plans->pluck('id')->toArray();
        $subscriberCounts = $this->planRepository->getSubscriberCountsForPlans($planIds);

        return $plans->map(function ($plan) use ($subscriberCounts) {
            $subscriberCount = $subscriberCounts[$plan->id] ?? 0;

            return [
                'plan' => $plan,
                'subscriber_count' => $subscriberCount,
                'revenue' => $this->calculatePlanRevenue($plan, $subscriberCount),
            ];
        })->toArray();
    }

    public function togglePlanActive(int $planId): bool
    {
        return $this->planRepository->toggleActive($planId);
    }

    public function togglePlanFeatured(int $planId): bool
    {
        return $this->planRepository->toggleFeatured($planId);
    }

    public function subscribeMemberToPlanWithVoucher(
        int $memberId,
        int $planId,
        int $siteId,
        ?string $voucherCode = null,
        array $paymentData = []
    ): Subscription
    {
        $plan = $this->planRepository->find($planId);

        if (!$plan) {
            throw new PlanNotFoundException("Plan with ID {$planId} not found");
        }

        if (!$plan->is_active) {
            throw new \InvalidArgumentException('Cannot subscribe to inactive plan');
        }

        $voucherId = null;
        $discountAmountCents = 0;
        $originalPriceCents = (int)round($plan->price * 100);

        if ($voucherCode) {
            $validation = $this->voucherService->validateVoucherForSubscription(
                $voucherCode,
                $planId,
                $memberId
            );

            if (!$validation->valid) {
                throw new \InvalidArgumentException($validation->message);
            }

            $voucherId = $validation->voucher->id;
            $discountAmountCents = (int)round($validation->discount * 100);

            if ($discountAmountCents > $originalPriceCents) {
                $discountAmountCents = $originalPriceCents;
            }
        }

        $subscriptionData = array_merge($paymentData, [
            'voucher_id' => $voucherId,
            'discount_amount' => $discountAmountCents / 100,
            'original_price' => $originalPriceCents / 100,
        ]);

        return $this->subscriptionRepository->createSubscription(
            $memberId,
            $planId,
            $siteId,
            $subscriptionData
        );
    }

    private function normaliseRegionSetIds(mixed $ids): array
    {
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : [];
        }
        return is_array($ids) ? array_map('intval', $ids) : [];
    }
}