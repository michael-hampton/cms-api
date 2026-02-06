<?php

namespace App\Services\Subscriptions;

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
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly VoucherService                 $voucherService,
        private readonly SubscriptionEligibilityService $eligibilityService
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

    public function createPlan(array $data, int $siteId): SubscriptionPlan
    {
        $planData = $this->preparePlanData($data, $siteId);
        return $this->planRepository->create($planData);
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
            $priceCents = is_float($data['price'])
                ? (int)round($data['price'] * 100)
                : (int)$data['price'];

            if ($priceCents < 0) {
                throw new \InvalidArgumentException('Price cannot be negative');
            }

            $prepared['price'] = $priceCents / 100;
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

    public function updatePlan(int $planId, array $data, int $siteId): ?SubscriptionPlan
    {
        $existingPlan = $this->planRepository->find($planId);

        if (!$existingPlan) {
            throw new PlanNotFoundException("Plan with ID {$planId} not found");
        }

        if ($existingPlan->site_id !== $siteId) {
            throw new \InvalidArgumentException('Cannot update plan from different site');
        }

        // Prevent slug changes if active subscriptions exist
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

        return [
            'plan' => $plan,
            'subscriber_count' => $this->planRepository->getSubscriberCount($planId),
            'revenue' => $this->calculatePlanRevenue($planId)
        ];
    }

    private function calculatePlanRevenue(int $planId): float
    {
        $plan = $this->planRepository->find($planId);
        if (!$plan) {
            return 0.0;
        }

        $activeCount = $this->planRepository->getSubscriberCount($planId);
        return $activeCount * $plan->price;
    }

    public function getAllPlansWithStats(int $siteId): array
    {
        $plans = $this->planRepository->getAllForSite($siteId);

        return $plans->map(function ($plan) {
            return [
                'plan' => $plan,
                'subscriber_count' => $this->planRepository->getSubscriberCount($plan->id),
                'revenue' => $this->calculatePlanRevenue($plan->id)
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
        int   $memberId,
        int   $planId,
        int   $siteId,
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

            $voucherId = $validation->voucherId;
            $discountAmountCents = (int)round($validation->discount * 100);

            // Ensure discount doesn't exceed price
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