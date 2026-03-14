<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class SubscriptionPlanPricingRepository extends Repository
{
    public function getForPlan(int $planId): Collection
    {
        $query = SubscriptionPlanPricing::where('is_active', true);

        if ($planId) {
            $query->where('plan_id', $planId);
        }

        return $query->orderBy('sort_order')->get();
    }

    public function findActiveDefaultForPlan(int $planId, ?int $excludePricingId = null): ?SubscriptionPlanPricing
    {
        $query = SubscriptionPlanPricing::where('plan_id', $planId)
            ->where('is_active', true)
            ->where('is_default', true);

        if ($excludePricingId !== null) {
            $query->where('id', '!=', $excludePricingId);
        }

        return $query->first();
    }

    public function findActiveBySortOrder(int $planId, int $sortOrder, ?int $excludePricingId = null): ?SubscriptionPlanPricing
    {
        $query = SubscriptionPlanPricing::where('plan_id', $planId)
            ->where('is_active', true)
            ->where('sort_order', $sortOrder);

        if ($excludePricingId !== null) {
            $query->where('id', '!=', $excludePricingId);
        }

        return $query->first();
    }

    public function getDefaultForPlan(int $planId): ?SubscriptionPlanPricing
    {
        return SubscriptionPlanPricing::where('plan_id', $planId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public function setAsDefault(int $pricingId): bool
    {
        $pricing = $this->find($pricingId);

        if (!$pricing) {
            return false;
        }

        // Remove default from all other pricing tiers for this plan
        SubscriptionPlanPricing::where('plan_id', $pricing->plan_id)
            ->where('id', '!=', $pricingId)
            ->update(['is_default' => false]);

        // Set this as default
        return $this->update($pricingId, ['is_default' => true]) !== null;
    }

    public function toggleActive(int $pricingId): bool
    {
        $pricing = $this->find($pricingId);

        if (!$pricing) {
            return false;
        }

        return $this->update($pricingId, [
                'is_active' => !$pricing->is_active
            ]) !== null;
    }

    public function updateSortOrders(array $orderMap): bool
    {
        foreach ($orderMap as $id => $sortOrder) {
            $this->update($id, ['sort_order' => $sortOrder]);
        }

        return true;
    }

    public function searchPricingTiersPaginated(array $filters, int $siteId): array
    {
        $query = SubscriptionPlanPricing::forSite($siteId);

        // Filter by plan_id
        if (!empty($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        // Filter by status (active/inactive)
        if (!empty($filters['status'])) {
            $isActive = $filters['status'] === 'active';
            $query->where('is_active', $isActive);
        }

        // Search in label and period_description
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('label', 'LIKE', "%{$search}%")
                    ->orWhere('period_description', 'LIKE', "%{$search}%");
            });
        }

        // Default ordering
        $query->orderBy('sort_order', 'asc');

        return $query->paginate(
            perPage: $filters['per_page'] ?? 15,
            page: $filters['page'] ?? 1
        );
    }

    /**
     * Get active pricing tiers for a plan (for querying/filtering)
     *
     * @param int $planId
     * @return QueryBuilder
     */
    public function getActiveTiersForPlan(int $planId): QueryBuilder
    {
        return SubscriptionPlanPricing::query()
            ->where('plan_id', $planId)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::create('subscription_plan_pricing');
        $engine = new SearchEngine($configuration);

        // Replace with however your repository accesses its base query builder,
        // e.g. Campaign::query() or $this->model->newQuery()
        return $engine->search($this->query(), $criteria);
    }

    protected function getModelClass(): string
    {
        return SubscriptionPlanPricing::class;
    }
}