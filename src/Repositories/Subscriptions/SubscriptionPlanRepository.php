<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\SubscriptionPlan;
use App\Repositories\Repository;

class SubscriptionPlanRepository extends Repository
{
    public function getActivePlans(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return SubscriptionPlan::where('site_id', $siteId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('price', 'asc')
            ->get();
    }

    public function getFeaturedPlans(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return SubscriptionPlan::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    public function findBySlug(string $slug, ?int $siteId = null): ?SubscriptionPlan
    {
        $siteId = $siteId ?? SiteContext::getId();

        return SubscriptionPlan::where('slug', $slug)
            ->where('site_id', $siteId)
            ->first();
    }

    public function getAllForSite(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return SubscriptionPlan::where('site_id', $siteId)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getSubscriberCount(int $planId): int
    {
        return SubscriptionPlan::find($planId)
            ?->activeSubscriptions()
            ->count() ?? 0;
    }

    public function toggleActive(int $planId): bool
    {
        $plan = $this->find($planId);
        if (!$plan) {
            return false;
        }

        return $this->update($planId, [
                'is_active' => !$plan->is_active
            ]) !== null;
    }

    public function toggleFeatured(int $planId): bool
    {
        $plan = $this->find($planId);
        if (!$plan) {
            return false;
        }

        return $this->update($planId, [
                'is_featured' => !$plan->is_featured
            ]) !== null;
    }

    public function updateSortOrder(array $orders): bool
    {
        foreach ($orders as $id => $order) {
            $this->update($id, ['sort_order' => $order]);
        }
        return true;
    }

    protected function getModelClass(): string
    {
        return SubscriptionPlan::class;
    }

    /**
     * Get upgrade plans for a given plan
     */
    public function getUpgradePlansFor(int $planId): Collection
    {
        return SubscriptionPlan::where('upgrade_from_plan_id', $planId)
            ->where('is_upgrade_option', true)
            ->where('is_active', true)
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Get all plans with Insider access
     */
    public function getInsiderPlans(?int $siteId = null): Collection
    {
        $query = SubscriptionPlan::where('includes_insider', true)
            ->where('is_active', true);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        return $query->orderBy('price', 'asc')->get();
    }
}