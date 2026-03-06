<?php

declare(strict_types=1);

namespace App\Services\Adverts;

use App\DTO\Merchant\AnalyticsSnapshot;
use App\Models\Merchant;
use App\Repositories\Adverts\MerchantAnalyticsRepository;

/**
 * Assembles offer-click, deal-click, and product-view analytics
 * for the merchant dashboard.
 *
 * Kept separate from MerchantStatsService because:
 *   - Different tables / data sources
 *   - Independent caching lifetime
 *   - May be called on-demand (AJAX) without triggering revenue queries
 */
class MerchantAnalyticsService
{
    public function __construct(
        private readonly MerchantAnalyticsRepository $analyticsRepository,
    )
    {
    }

    /**
     * Return a fully assembled analytics snapshot for the dashboard.
     *
     * @param int $days Window size. 7, 30, or 90.
     */
    public function forMerchant(Merchant $merchant, int $days = 30): AnalyticsSnapshot
    {
        $id = $merchant->id;

        $offerTotals = $this->analyticsRepository->offerClickTotals($id, $days);
        $dealTotals = $this->analyticsRepository->dealClickTotals($id, $days);
        $viewTotals = $this->analyticsRepository->productViewTotals($id, $days);
        $comparison = $this->analyticsRepository->periodComparison($id, $days);

        $offerClicksByDay = $this->analyticsRepository->offerClicksByDay($id, $days);
        $dealClicksByDay = $this->analyticsRepository->dealClicksByDay($id, $days);
        $productViewsByDay = $this->analyticsRepository->productViewsByDay($id, $days);

        $topOffers = $this->analyticsRepository->offerClicksByOffer($id, $days);
        $topDealProducts = $this->analyticsRepository->dealClicksByProduct($id, $days);
        $topViewedProducts = $this->analyticsRepository->productViewsByProduct($id, $days);

        return new AnalyticsSnapshot(
            days: $days,
            offerClicks: $offerTotals['click'],
            offerRenders: $offerTotals['render'],
            offerClickDelta: $comparison['deltas']['offer_clicks'],
            dealClicks: $dealTotals['click'],
            dealRenders: $dealTotals['render'],
            dealClickDelta: $comparison['deltas']['deal_clicks'],
            productViews: $viewTotals['total'],
            productViewsUnique: $viewTotals['unique_users'],
            productViewDelta: $comparison['deltas']['product_views'],
            offerClicksByDay: $this->collectionToArray($offerClicksByDay),
            dealClicksByDay: $this->collectionToArray($dealClicksByDay),
            productViewsByDay: $this->collectionToArray($productViewsByDay),
            topOffers: $this->collectionToArray($topOffers),
            topDealProducts: $this->collectionToArray($topDealProducts),
            topViewedProducts: $this->collectionToArray($topViewedProducts),
        );
    }

    /**
     * Convert a Collection (or any iterable) to a plain, numerically-indexed
     * PHP array. Using json_encode on a Collection directly, or on an
     * associatively-keyed array, produces a JSON object {} instead of an
     * array [] — which breaks the chart JS that calls data.length.
     */
    private function collectionToArray(iterable $collection): array
    {
        $items = [];
        foreach ($collection as $item) {
            $items[] = (array)$item;
        }
        return array_values($items);
    }

    /**
     * Aggregate CTR across all offers for a quick headline figure.
     */
    public function overallOfferCtr(Merchant $merchant, int $days = 30): float
    {
        $totals = $this->analyticsRepository->offerClickTotals($merchant->id, $days);

        if ($totals['render'] === 0) {
            return 0.0;
        }

        return round(($totals['click'] / $totals['render']) * 100, 1);
    }
}