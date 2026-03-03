<?php

namespace App\Services\Subscriptions;

use App\Actions\Subscriptions\CreatePlanAction;
use App\Enums\Subscriptions\BillingPeriod;
use App\Exceptions\Subscriptions\AlreadySubscribedException;
use App\Exceptions\Subscriptions\PlanHasActiveSubscriptionsException;
use App\Exceptions\Subscriptions\PlanNotFoundException;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Vouchers\VoucherService;

class SubscriptionPlanService
{
    private const ALLOWED_CURRENCIES = ['USD', 'EUR', 'GBP', 'AUD', 'CAD'];

    public function __construct(
        private readonly SubscriptionPlanRepository     $planRepository,
        private readonly SubscriptionRepository         $subscriptionRepository,
        private readonly VoucherService                 $voucherService,
        private readonly SubscriptionEligibilityService $eligibilityService,
        private readonly CreatePlanAction               $createPlanAction,
        private readonly SubscriptionPlanPricingService $pricingService,
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
        $planData = $this->preparePlanData($data, $siteId);

        $plan = $this->createPlanAction->execute($planData);

        if ($this->hasPriceData($data)) {
            $pricingData = $this->preparePricingData($data);
            $this->pricingService->createPricingTier($plan->id, $pricingData);
        }

        return $plan;
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

    private function preparePlanData(array $data, ?int $siteId = null): array
    {
        $prepared = [];

        if ($siteId) {
            $prepared['site_id'] = $siteId;
        }

        if (isset($data['name'])) {
            $prepared['name'] = $data['name'];

            if (empty($data['slug'])) {
                $prepared['slug'] = $this->generateSlug($data['name']);
            }
        }

        if (!empty($data['slug'])) {
            $prepared['slug'] = $this->sanitizeSlug($data['slug']);
        }

        if (isset($data['description'])) {
            $prepared['description'] = $data['description'];
        }

        if (isset($data['price'])) {
            $price = (float)$data['price'];

            if ($price < 0) {
                throw new \InvalidArgumentException('Price cannot be negative');
            }

            $prepared['price'] = round($price, 2);
        }

        if (isset($data['currency'])) {
            $currency = strtoupper($data['currency']);

            if (!in_array($currency, self::ALLOWED_CURRENCIES)) {
                throw new \InvalidArgumentException("Currency {$currency} is not supported");
            }

            $prepared['currency'] = $currency;
        }

        if (isset($data['billing_period'])) {
            $billingPeriod = BillingPeriod::tryFrom($data['billing_period']);

            if (!$billingPeriod) {
                throw new \InvalidArgumentException("Invalid billing period: {$data['billing_period']}");
            }

            $prepared['billing_period'] = $billingPeriod->value;
        }

        if (isset($data['trial_days'])) {
            $trialDays = (int)$data['trial_days'];

            if ($trialDays < 0) {
                throw new \InvalidArgumentException('Trial days cannot be negative');
            }

            $prepared['trial_days'] = $trialDays;
        }

        if (isset($data['features'])) {
            $prepared['features'] = is_array($data['features'])
                ? $data['features']
                : json_decode($data['features'], true);
        }

        if (isset($data['is_active'])) {
            $prepared['is_active'] = (bool)$data['is_active'];
        }

        if (isset($data['is_featured'])) {
            $prepared['is_featured'] = (bool)$data['is_featured'];
        }

        if (isset($data['sort_order'])) {
            $prepared['sort_order'] = (int)$data['sort_order'];
        }

        if (isset($data['digital_download_url'])) {
            $prepared['digital_download_url'] = $data['digital_download_url'];
        }

        if (isset($data['print_shipping_required'])) {
            $prepared['print_shipping_required'] = (bool)$data['print_shipping_required'];
        }

        if (isset($data['includes_insider'])) {
            $prepared['includes_insider'] = (bool)$data['includes_insider'];
        }

        if (isset($data['is_upgrade_option'])) {
            $prepared['is_upgrade_option'] = (bool)$data['is_upgrade_option'];
        }

        if (isset($data['upgrade_from_plan_id'])) {
            $prepared['upgrade_from_plan_id'] = $data['upgrade_from_plan_id'] !== null
                ? (int)$data['upgrade_from_plan_id']
                : null;
        }

        if (isset($data['dispatch_days'])) {
            $prepared['dispatch_days'] = (int)$data['dispatch_days'];
        }

        if (isset($data['release_date'])) {
            $prepared['release_date'] = $data['release_date'];
        }

        if (isset($data['pre_release_enabled'])) {
            $prepared['pre_release_enabled'] = (bool)$data['pre_release_enabled'];
        }

        if (isset($data['categories'])) {
            $prepared['categories'] = is_array($data['categories'])
                ? $data['categories']
                : json_decode($data['categories'], true);
        }

        if (isset($data['tags'])) {
            $prepared['tags'] = is_array($data['tags'])
                ? $data['tags']
                : json_decode($data['tags'], true);
        }

        if (isset($data['premium_access'])) {
            $prepared['premium_access'] = is_array($data['premium_access'])
                ? $data['premium_access']
                : json_decode($data['premium_access'], true);
        }

        return $prepared;
    }

    private function generateSlug(string $name): string
    {
        return Str::slug($name);
    }

    private function sanitizeSlug(string $slug): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', $slug), '-'));
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

        $planData = $this->preparePlanData($data);
        return $this->planRepository->update($planId, $planData);
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

        return $plans->map(function ($plan) {
            $subscriberCount = $this->planRepository->getSubscriberCount($plan->id);

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
}