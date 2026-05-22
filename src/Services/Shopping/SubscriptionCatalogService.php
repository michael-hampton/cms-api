<?php

namespace App\Services\Shopping;

use App\Enums\Subscriptions\SubscriptionSortOption;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Support\Collection;
use App\Models\Member;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;

class SubscriptionCatalogService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository
    )
    {
    }

    /**
     * Return a paginated catalog of active one-time subscription plans,
     * filtered by the given criteria and optionally restricted to plans
     * visible to the provided member based on their territory.
     *
     * Visibility rules (mirrors Page / Newsletter behaviour):
     *  - null member or member with no territory → sees all plans
     *  - member with territory → sees plans with no region-set restrictions
     *    OR plans whose region sets include the member's territory
     */
    public function getCatalog(array $filters = [], ?Member $member = null): array
    {
        $query = $this->planRepository->buildCatalogQuery();

        // Territory / region-set visibility
        $query->visibleToMember($member);

        // Search
        if (!empty($filters['search'])) {
            $query = $this->applySearch($query, $filters['search']);
        }

        // Site filter
        if (!empty($filters['site_id'])) {
            $query = $query->where('site_id', $filters['site_id']);
        }

        // Delivery type filter
        if (!empty($filters['delivery_type'])) {
            $query = $this->applyDeliveryTypeFilter($query, $filters['delivery_type']);
        }

        // Price range — filters against active tier prices (COALESCE(sale_price, price)),
        // falling back to the plan-level price for plans without tiers.
        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $query = $query->whereHas('pricingTiers', function ($q) use ($filters) {
                if (!empty($filters['price_min'])) {
                    $q->whereRaw('COALESCE(sale_price, price) >= ?', [$filters['price_min']]);
                }
                if (!empty($filters['price_max'])) {
                    $q->whereRaw('COALESCE(sale_price, price) <= ?', [$filters['price_max']]);
                }
            });
        }

        // Featured filter
        if (!empty($filters['featured']) && $filters['featured'] === 'true') {
            $query = $query->where('is_featured', true);
        }

        // Category filter
        if (!empty($filters['category'])) {
            $query = $query->where('category', $filters['category']);
        }

        // Tag filter
        if (!empty($filters['tags']) && is_array($filters['tags'])) {
            $query->whereJsonContains('tags', $filters['tags']);
        }

        // Special filter (on_sale or limited_offer)
        if (!empty($filters['special_filter'])) {
            $query = $this->applySpecialFilter($query, $filters['special_filter']);
        }

        if (!empty($filters['categories']) && is_array($filters['categories'])) {
            $query->whereJsonContains('categories', $filters['categories']);
        }

        // Sorting — price sorts use the `lowest_effective_price` computed column
        // added by buildCatalogQuery(); non-price sorts use plan columns directly.
        $sortOption = !empty($filters['sort'])
            ? SubscriptionSortOption::from($filters['sort'])
            : SubscriptionSortOption::PRICE_LOW_TO_HIGH;

        [$column, $direction] = $sortOption->orderByClause();
        $query = $query->orderBy($column, $direction);

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        return $query->paginate($perPage, $page);
    }

    private function applySearch($query, string $searchTerm): mixed
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('description', 'LIKE', "%{$searchTerm}%");
        });
    }

    private function applyDeliveryTypeFilter($query, string $deliveryType): mixed
    {
        if ($deliveryType === SubscriptionType::DIGITAL->value) {
            return $query->whereNotNull('digital_download_url')
                ->where('digital_download_url', '!=', '');
        }

        if ($deliveryType === SubscriptionType::PRINTED->value) {
            return $query->where('print_shipping_required', true);
        }

        return $query;
    }

    private function applySpecialFilter($query, string $specialFilter): mixed
    {
        if ($specialFilter === 'on_sale') {
            return $query->whereHas('pricingTiers', function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNotNull('sale_price')
                        ->whereRaw('sale_price < price');
                })->orWhere(function ($sq) {
                    $sq->whereNotNull('digital_sale_price')
                        ->whereRaw('digital_sale_price < price');
                });
            });
        }

        if ($specialFilter === 'limited_offer') {
            $now = now_datetime();
            $thirtyDaysFromNow = $now->addDays(30);

            return $query->whereNotNull('end_date')
                ->where('end_date', '>=', $now)
                ->where('end_date', '<=', $thirtyDaysFromNow);
        }

        return $query;
    }

    public function getAvailableSites(): Collection
    {
        return $this->planRepository->getSitesWithActivePlans();
    }

    public function getPriceRange(?int $siteId = null): array
    {
        return $this->planRepository->getPriceRange($siteId);
    }

    public function getAvailableCategories(?int $siteId = null): array
    {
        return $this->planRepository->getDistinctCategories($siteId);
    }

    public function getAvailableTags(?int $siteId = null): array
    {
        return $this->planRepository->getDistinctTags($siteId);
    }

    /**
     * Return the lowest effective price for a plan broken down by delivery type.
     *
     * For plans with pricing tiers the plan-level `price` field is stale and
     * must be ignored. Each tier carries its own print price (sale_price ?? price)
     * and optionally a separate digital price (digital_price, with the same
     * sale_price ?? price fallback when digital_price is absent).
     *
     * For plans without any pricing tiers callers should use the plan's `price`
     * field directly; this method returns nulls in that case so the caller can
     * decide how to render the fallback.
     */
    public function getLowestPriceForPlan($plan): array
    {
        $lowestPrint = null;
        $lowestDigital = null;

        foreach ($plan->pricingTiers as $tier) {
            // Effective print price for this tier.
            $effectivePrintPrice = $tier->sale_price ?? $tier->price;

            if ($plan->hasPrintOption()) {
                if ($lowestPrint === null || $effectivePrintPrice < $lowestPrint) {
                    $lowestPrint = $effectivePrintPrice;
                }
            }

            if ($plan->hasDigitalOption()) {
                $effectiveDigitalPrice =
                    $tier->digital_sale_price
                    ?? $tier->digital_price
                    ?? $tier->sale_price
                    ?? $tier->price;

                if ($lowestDigital === null || $effectiveDigitalPrice < $lowestDigital) {
                    $lowestDigital = $effectiveDigitalPrice;
                }
            }
        }

        $candidates = array_filter([$lowestPrint, $lowestDigital], fn($v) => $v !== null);

        return [
            SubscriptionType::PRINTED->value => $lowestPrint,
            SubscriptionType::DIGITAL->value => $lowestDigital,
            'lowest' => !empty($candidates) ? min($candidates) : null,
        ];
    }
}