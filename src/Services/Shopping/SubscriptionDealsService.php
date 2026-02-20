<?php

namespace App\Services\Shopping;

use App\Repositories\Subscriptions\SubscriptionBundleRepository;

/**
 * SubscriptionDealsService
 *
 * Provides the data for the /subscriptions/deals page.
 *
 * "Deals" means: subscription plans where at least one pricing tier has a
 * sale_price that is lower than its list price.
 *
 * Reuses SubscriptionCatalogService with the existing `on_sale` special filter
 * — no query logic lives here.
 *
 * Subscription bundles are also surfaced on the deals page: a bundle by
 * definition has bundle_price < total_price, so all active bundles qualify.
 */
class SubscriptionDealsService
{
    public function __construct(
        private readonly SubscriptionCatalogService   $catalogService,
        private readonly SubscriptionBundleRepository $bundleRepository
    )
    {
    }

    /**
     * Return paginated on-sale subscription plans for the deals page.
     *
     * Delegates entirely to the catalog service — this method only enforces
     * that `special_filter` is always `on_sale`.
     *
     * @param array $filters Accepts same keys as SubscriptionCatalogService::getCatalog()
     *                       except `special_filter` which is always overridden.
     */
    public function getDeals(array $filters = []): array
    {
        $filters['special_filter'] = 'on_sale';

        return $this->catalogService->getCatalog($filters);
    }

    /**
     * Return all active subscription bundles for the deals page.
     *
     * Bundles are separate from individual plans; they're displayed in their
     * own section on the deals and main listing pages.
     */
    public function getActiveBundles(?int $siteId = null): array
    {
        return $this->bundleRepository
            ->getActiveBundles($siteId)
            ->map(fn($bundle) => $this->formatBundle($bundle))
            ->toArray();
    }

    private function formatBundle(\App\Models\SubscriptionBundle $bundle): array
    {
        return [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'slug' => $bundle->slug,
            'description' => $bundle->description,
            'bundle_price' => $bundle->bundle_price,
            'total_price' => $bundle->total_price,
            'savings_amount' => $bundle->getSavingsAmount(),
            'discount_percentage' => $bundle->getDiscountPercentage(),
            'plans' => $bundle->items->map(fn($item) => [
                'id' => $item->subscriptionPlan->id,
                'name' => $item->subscriptionPlan->name,
                'delivery_type' => $item->delivery_type,
                'quantity' => $item->quantity,
            ])->toArray(),
        ];
    }
}