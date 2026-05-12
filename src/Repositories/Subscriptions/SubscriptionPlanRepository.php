<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanRegionSet;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class SubscriptionPlanRepository extends Repository
{
    public function getActivePlans(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return SubscriptionPlan::with(['pricingTiers'])->where('site_id', $siteId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('price', 'asc')
            ->get();
    }

    public function getFeaturedPlans(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return SubscriptionPlan::where('site_id', $siteId)
            ->active()
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

    /**
     * Get subscriber counts for multiple plans in a single query.
     *
     * @param array<int,int> $planIds
     * @return array<int,int> plan_id => count
     */
    public function getSubscriberCountsForPlans(array $planIds): array
    {
        if (empty($planIds)) {
            return [];
        }

        $rows = Subscription::whereIn('plan_id', $planIds)
            ->active()
            ->groupBy('plan_id')
            ->selectRaw('plan_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'plan_id')
            ->toArray();

        // Ensure all requested IDs are present with at least 0
        $counts = [];
        foreach ($planIds as $id) {
            $counts[$id] = (int)($rows[$id] ?? 0);
        }

        return $counts;
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
            ->active()
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

    public function lockForUpdate(int $planId): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('id', $planId)
            //->lockForUpdate()
            ->first();
    }

    /**
     * Build base query for catalog with eager loading.
     *
     * Adds a `lowest_effective_price` computed column so the catalog can sort
     * by real tier prices rather than the (potentially stale) plan-level price.
     *
     * Logic per plan:
     *   - If active pricing tiers exist → MIN(COALESCE(sale_price, price))
     *   - If no active tiers exist      → fall back to the plan's own `price`
     *
     * The outer COALESCE handles the no-tiers case cleanly without any PHP-side
     * branching; the subquery simply returns NULL when no rows match, and
     * COALESCE replaces that NULL with subscription_plans.price.
     */
    public function buildCatalogQuery()
    {
        return SubscriptionPlan::with(['site', 'pricingTiers'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('plan_type', 'onetime')
                    ->orWhere('plan_type', 'recurring');
            })
            ->selectRaw('
                subscription_plans.*,
                COALESCE(
                    (
                        SELECT MIN(COALESCE(spp.sale_price, spp.price))
                        FROM subscription_plan_pricing spp
                        WHERE spp.plan_id = subscription_plans.id
                          AND spp.is_active = 1
                    ),
                    subscription_plans.price
                ) AS lowest_effective_price
            ');
    }

    /**
     * Get sites that have active one-time subscription plans
     */
    public function getSitesWithActivePlans(): Collection
    {
        return Site::whereHas('subscriptionPlans', function ($q) {
            $q->where('is_active', true)
                ->where('plan_type', 'onetime');
        })
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get price range for active plans
     */
    public function getPriceRange(?int $siteId = null): array
    {
        $query = SubscriptionPlan::where('is_active', true)
            ->where('plan_type', 'onetime');

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $min = $query->min('price') ?? 0;
        $max = $query->max('price') ?? 0;

        return [
            'min' => $min,
            'max' => $max,
        ];
    }

    /**
     * Find plan with pricing tiers eager loaded
     */
    public function findWithPricingTiers(int $planId): ?SubscriptionPlan
    {
        return SubscriptionPlan::with(['pricingTiers'])
            ->where('id', $planId)
            ->first();
    }

    public function findBySlugWithPricingTiers(string $slug): ?SubscriptionPlan
    {
        return SubscriptionPlan::with(['pricingTiers'])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Get distinct categories from active plans (if you have categories)
     */
    public function getDistinctCategories(?int $siteId = null): array
    {
        $query = SubscriptionPlan::where('is_active', true)
            ->where('plan_type', 'onetime')
            ->whereNotNull('categories');

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $plans = $query->get();
        $allCategories = [];

        foreach ($plans as $plan) {
            if (is_array($plan->categories)) {
                $allCategories = array_merge($allCategories, $plan->categories);
            }
        }

        return array_values(array_unique($allCategories));
    }

    /**
     * Get distinct tags from active plans (if you have tags JSON field)
     */
    public function getDistinctTags(?int $siteId = null): array
    {
        $query = SubscriptionPlan::where('is_active', true)
            ->where('plan_type', 'onetime')
            ->whereNotNull('tags');

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $plans = $query->get();
        $allTags = [];

        foreach ($plans as $plan) {
            if (is_array($plan->tags)) {
                $allTags = array_merge($allTags, $plan->tags);
            }
        }

        return array_values(array_unique($allTags));
    }

    public function syncRegionSets(SubscriptionPlan $plan, array $ids): void
    {
        SubscriptionPlanRegionSet::where('subscription_plan_id', $plan->id)->delete();

        foreach ($ids as $regionSetId) {
            SubscriptionPlanRegionSet::create([
                'subscription_plan_id' => $plan->id,
                'region_set_id' => $regionSetId,
            ]);
        }
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::create('subscription_plan');
        $engine = new SearchEngine($configuration);

        return $engine->search($this->query(), $criteria);
    }
}