<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\DTO\Merchant\DashboardStats;
use App\Models\Merchant;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\ReviewRepository;

/**
 * Assembles the four overview stat cards and the 30-day revenue chart series.
 *
 * Kept separate from the controller because each metric has an independent
 * reason to change (different query strategies, caching lifetimes, etc.)
 */
class MerchantStatsService
{
    public function __construct(
        private readonly OrderRepository   $orderRepository,
        private readonly ProductRepository $productRepository,
        private readonly ReviewRepository  $reviewRepository,
    )
    {
    }

    public function forMerchant(Merchant $merchant): DashboardStats
    {
        $currentMonth = $this->orderRepository->monthlyStatsForMerchant($merchant->id);
        $previousMonth = $this->orderRepository->monthlyStatsForMerchant($merchant->id, monthsAgo: 1);
        $chartSeries = $this->orderRepository->dailyRevenueForMerchant($merchant->id, days: 30);
        $avgRating = $this->reviewRepository->averageRatingForMerchant($merchant->id);
        $previousRating = $this->reviewRepository->averageRatingForMerchant($merchant->id, monthsAgo: 1);
        $impressions = $this->productRepository->totalImpressionsForMerchant($merchant->id);
        $previousImpressions = $this->productRepository->totalImpressionsForMerchant($merchant->id, monthsAgo: 1);

        return new DashboardStats(
            totalRevenue: $currentMonth->totalRevenue,
            revenueDelta: $this->percentageDelta($previousMonth->totalRevenue, $currentMonth->totalRevenue),
            totalOrders: $currentMonth->totalOrders,
            ordersDelta: $this->percentageDelta($previousMonth->totalOrders, $currentMonth->totalOrders),
            totalImpressions: $impressions,
            impressionsDelta: $this->percentageDelta($previousImpressions, $impressions),
            averageRating: $avgRating,
            ratingDelta: round($avgRating - $previousRating, 1),
            chartSeries: $chartSeries->toArray(),
        );
    }

    private function percentageDelta(float|int $previous, float|int $current): float
    {
        if ($previous == 0) {
            return 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}