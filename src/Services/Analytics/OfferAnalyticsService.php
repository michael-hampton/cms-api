<?php

namespace App\Services\Analytics;

use App\Repositories\Offers\ProductOfferRepository;

class OfferAnalyticsService
{
    public function __construct(
        private readonly ProductOfferRepository $repository
    )
    {
    }

    public function getAllOfferStatistics(int $siteId): array
    {
        $offers = $this->repository->all();

        $totalOffers = $offers->count();
        $activeOffers = $offers->where('is_active', true)->count();
        $publishedOffers = $offers->where('status', 'published')->count();
        $pendingOffers = $offers->where('status', 'pending')->count();
        $rejectedOffers = $offers->where('status', 'rejected')->count();

        $now = now_datetime();
        $runningOffers = $offers->filter(function ($offer) use ($now) {
            return $offer->is_active
                && $offer->status === 'published'
                && $offer->start_date <= $now
                && $offer->end_date >= $now;
        })->count();

        $offerIds = $offers->pluck('id')->toArray();
        $clickStats = $this->getOfferClickStatistics($offerIds);

        $topOffers = $offers
            ->where('status', 'published')
            ->sortByDesc('discount_percentage')
            ->take(10)
            ->map(fn($offer) => [
                'id' => $offer->id,
                'product_name' => $offer->product_name ?? 'Unknown',
                'merchant_name' => $offer->merchant_name ?? 'N/A',
                'discount_percentage' => $offer->discount_percentage,
                'sale_price' => $offer->sale_price,
                'clicks' => $clickStats['by_offer'][$offer->id] ?? 0
            ])
            ->values()
            ->toArray();

        return [
            'total_offers' => $totalOffers,
            'active_offers' => $activeOffers,
            'running_offers' => $runningOffers,
            'published_offers' => $publishedOffers,
            'pending_offers' => $pendingOffers,
            'rejected_offers' => $rejectedOffers,
            'total_clicks' => $clickStats['total'] ?? 0,
            'unique_clickers' => $clickStats['unique'] ?? 0,
            'click_through_rate' => $totalOffers > 0 && isset($clickStats['unique'])
                ? round(($clickStats['unique'] / $totalOffers) * 100, 2)
                : 0,
            'top_offers' => $topOffers
        ];
    }

    private function getOfferClickStatistics(array $offerIds): array
    {
        if (empty($offerIds)) {
            return [
                'total' => 0,
                'unique' => 0,
                'by_offer' => []
            ];
        }

        return $this->repository->getClickStatistics($offerIds);
    }
}