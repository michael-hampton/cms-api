<?php

namespace App\Services\Shopping;

use App\Enums\Subscriptions\SubscriptionSortOption;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Support\Collection;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;

class SubscriptionCatalogService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository
    )
    {
    }

    public function getCatalog(array $filters = []): array
    {
        $query = $this->planRepository->buildCatalogQuery();

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

        // Price range
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

        // Category filter (if you have categories)
        if (!empty($filters['category'])) {
            $query = $query->where('category', $filters['category']);
        }

        // Tag filter (if you have tags)
        if (!empty($filters['tags']) && is_array($filters['tags'])) {
            $query->whereJsonContains('tags', $filters['tags']);
        }

        // Apply special filter (on_sale or limited_offer)
        if (!empty($filters['special_filter'])) {
            $query = $this->applySpecialFilter($query, $filters['special_filter']);
        }


        if (!empty($filters['categories']) && is_array($filters['categories'])) {
            $query->whereJsonContains('categories', $filters['categories']);
        }

        // Sorting
        $sortOption = !empty($filters['sort'])
            ? SubscriptionSortOption::from($filters['sort'])
            : SubscriptionSortOption::PRICE_LOW_TO_HIGH;

        [$column, $direction] = $sortOption->orderByClause();
        $query = $query->orderBy($column, $direction);

        // Pagination
        $perPage = $filters['per_page'] ?? 12;
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

    /**
     * Apply special filter for sales and limited offers
     */
    private function applySpecialFilter($query, string $specialFilter): mixed
    {
        if ($specialFilter === 'on_sale') {
            // Plans with sale prices OR original prices showing discount
            return $query->whereHas('pricingTiers', function ($q) {
                $q->where(function ($sq) {
                    // Has sale price
                    $sq->whereNotNull('sale_price')
                        ->whereRaw('sale_price < price');
                })->orWhere(function ($sq) {
                    // OR has original price discount
                    $sq->whereNotNull('original_price')
                        ->whereRaw('original_price > price');
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

    public function getLowestPriceForPlan($plan): array
    {
        $lowestPrint = null;
        $lowestDigital = null;

        foreach ($plan->pricingTiers as $tier) {
            $effectivePrice = $tier->sale_price ?? $tier->price;

            // Check print price
            if ($plan->hasPrintOption()) {
                if ($lowestPrint === null || $effectivePrice < $lowestPrint) {
                    $lowestPrint = $effectivePrice;
                }
            }

            // Check digital price
            if ($plan->hasDigitalOption()) {
                $digitalPrice = $tier->digital_price ?? $effectivePrice;
                if ($lowestDigital === null || $digitalPrice < $lowestDigital) {
                    $lowestDigital = $digitalPrice;
                }
            }
        }

        return [
            SubscriptionType::PRINTED->value => $lowestPrint,
            SubscriptionType::DIGITAL->value => $lowestDigital,
            'lowest' => min(array_filter([$lowestPrint, $lowestDigital]))
        ];
    }
}