<?php

namespace App\Services;

use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionRepository;

class SubscriptionPlanService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionRepository     $subscriptionRepository
    )
    {
    }

    public function getAvailablePlans(int $siteId): Collection
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

            // Auto-generate slug if not provided
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
            $prepared['price'] = (float)$data['price'];
        }

        if (isset($data['currency'])) {
            $prepared['currency'] = strtoupper($data['currency']);
        }

        if (isset($data['billing_period'])) {
            $prepared['billing_period'] = $data['billing_period'];
        }

        if (isset($data['trial_days'])) {
            $prepared['trial_days'] = (int)$data['trial_days'];
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

    public function updatePlan(int $planId, array $data): ?SubscriptionPlan
    {
        $planData = $this->preparePlanData($data);
        return $this->planRepository->update($planId, $planData);
    }

    public function deletePlan(int $planId): bool
    {
        // Check if plan has active subscriptions
        $activeCount = $this->planRepository->getSubscriberCount($planId);

        if ($activeCount > 0) {
            throw new \Exception("Cannot delete plan with active subscriptions");
        }

        return $this->planRepository->delete($planId);
    }

    public function subscribeMemberToPlan(
        int   $memberId,
        int   $planId,
        int   $siteId,
        array $paymentData = []
    ): Subscription
    {
        // Check if member already has active subscription
        if ($this->subscriptionRepository->hasActiveSubscriptionToPlan($memberId, $planId, $siteId)) {
            throw new \Exception("Member already has an active subscription to this plan");
        }

        // Create subscription
        return $this->subscriptionRepository->createSubscription(
            $memberId,
            $planId,
            $siteId,
            $paymentData
        );
    }

    public function canMemberSubscribe(int $memberId, int $planId, int $siteId): array
    {
        $plan = $this->planRepository->find($planId);

        if (!$plan || !$plan->is_active) {
            return [
                'can_subscribe' => false,
                'reason' => 'Plan not available'
            ];
        }

        if ($this->subscriptionRepository->hasActiveSubscriptionToPlan($memberId, $planId, $siteId)) {
            return [
                'can_subscribe' => false,
                'reason' => 'Already subscribed to this plan'
            ];
        }

        // Check if member has any active subscription
        $activeSubscription = $this->subscriptionRepository->getActiveSubscriptionForMember($memberId, $siteId);

        if ($activeSubscription) {
            return [
                'can_subscribe' => false,
                'reason' => 'Already has an active subscription',
                'current_plan' => $activeSubscription->plan_name
            ];
        }

        return [
            'can_subscribe' => true,
            'plan' => $plan
        ];
    }

    public function getPlanWithStats(int $planId): array
    {
        $plan = $this->planRepository->find($planId);

        if (!$plan) {
            return [];
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
}