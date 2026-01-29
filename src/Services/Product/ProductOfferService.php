<?php

namespace App\Services\Product;

use App\Framework\Authorization\AuthenticationService;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\OfferClicks;
use App\Models\ProductOffer;
use App\Repositories\Product\ProductOfferRepository;
use Exception;

class ProductOfferService
{
    public function __construct(
        private readonly ProductOfferRepository $repository,
        private readonly AuthenticationService  $authenticationService
    )
    {
    }

    public function getOffer(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function getActiveOffersForProduct(int $productId): Collection
    {
        return $this->repository->getActiveOffersForProduct($productId);
    }

    public function getActiveOffersForCategory(int $categoryId): Collection
    {
        return $this->repository->getActiveOffersForCategory($categoryId);
    }

    public function createOffer(array $data): Model
    {
        $this->validateOfferDates($data['start_date'], $data['end_date']);

        // Auto-fill status-related fields
        $data = $this->fillStatusFields($data);

        return $this->repository->create($data);
    }

    private function validateOfferDates(string $startDate, string $endDate): void
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        if ($start === false || $end === false) {
            throw new Exception('Invalid date format');
        }

        if ($end <= $start) {
            throw new Exception('End date must be after start date');
        }
    }

    public function updateOffer(int $id, array $data): ?ProductOffer
    {
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $this->validateOfferDates($data['start_date'], $data['end_date']);
        }

        // Get current offer to check status changes
        $currentOffer = $this->repository->find($id);
        if ($currentOffer && isset($data['status'])) {
            $data = $this->fillStatusFieldsOnUpdate($data, $currentOffer);
        }

        return $this->repository->update($id, $data);
    }

    private function fillStatusFields(array $data): array
    {
        if (!isset($data['status'])) {
            return $data;
        }

        $userId = $this->authenticationService->getUserId();
        if (!$userId) {
            return $data;
        }

        if ($data['status'] === 'published') {
            $data['published_by'] = $userId;
            $data['published_at'] = now_datetime();
        } elseif ($data['status'] === 'rejected') {
            $data['rejected_by'] = $userId;
            $data['rejected_at'] = now_datetime();
        }

        return $data;
    }

    private function fillStatusFieldsOnUpdate(array $data, ProductOffer $currentOffer): array
    {
        // Only fill if status is changing
        if ($data['status'] === $currentOffer->status) {
            return $data;
        }

        $userId = $this->authenticationService->getUserId();
        if (!$userId) {
            return $data;
        }

        if ($data['status'] === 'published' && !$currentOffer->published_at) {
            $data['published_by'] = $userId;
            $data['published_at'] = now_datetime();
        } elseif ($data['status'] === 'rejected' && !$currentOffer->rejected_at) {
            $data['rejected_by'] = $userId;
            $data['rejected_at'] = now_datetime();
        }

        return $data;
    }

    public function deleteOffer(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function hasActiveOffer(int $productId): bool
    {
        return $this->repository->hasActiveOffer($productId);
    }

    public function publish(int $id, int $userId): ?ProductOffer
    {
        return $this->repository->publish($id, $userId);
    }

    public function reject(int $id, int $userId, string $reason): ?ProductOffer
    {
        if (empty($reason)) {
            throw new Exception('Rejection reason is required');
        }

        return $this->repository->reject($id, $userId, $reason);
    }

    public function searchOffers(array $filters): Collection
    {
        return $this->repository->search($filters);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->repository->getByStatus($status);
    }

    public function getAllOfferStatistics(int $siteId): array
    {
        $offers = ProductOffer::with(['product'])->get();

        $totalOffers = $offers->count();
        $activeOffers = $offers->where('is_active', true)->count();
        $publishedOffers = $offers->where('status', 'published')->count();
        $pendingOffers = $offers->where('status', 'pending')->count();
        $rejectedOffers = $offers->where('status', 'rejected')->count();

        // Calculate currently running offers (published, active, and within date range)
        $now = now_datetime();
        $runningOffers = $offers->filter(function ($offer) use ($now) {
            return $offer->is_active
                && $offer->status === 'published'
                && $offer->start_date <= $now
                && $offer->end_date >= $now;
        })->count();

        // Get click statistics if you have an offer_clicks table
        $offerIds = $offers->pluck('id')->toArray();
        $clickStats = $this->getOfferClickStatistics($offerIds);

        // Top performing offers
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
            'click_through_rate' => $totalOffers > 0 && isset($clickStats['unique']) ? round(($clickStats['unique'] / $totalOffers) * 100, 2) : 0,
            'top_offers' => $topOffers
        ];
    }

    private function getOfferClickStatistics(array $offerIds): array
    {
        return [];

        // Assuming you have an offer_clicks table
        if (empty($offerIds)) {
            return [
                'total' => 0,
                'unique' => 0,
                'by_offer' => []
            ];
        }

        $totalClicks = OfferClicks::whereIn('offer_id', $offerIds)
            ->count();

        OfferClicks::whereIn('offer_id', $offerIds)
            ->distinct('user_identifier')
            ->count('user_identifier');

        OfferClicks::whereIn('offer_id', $offerIds)
            ->select('offer_id')
            ->selectRaw('COUNT(*) as clicks')
            ->groupBy('offer_id')
            ->get()
            ->pluck('clicks', 'offer_id')
            ->toArray();

        return [
            'total' => $totalClicks,
            'unique' => $uniqueClickers,
            'by_offer' => $clicksByOffer
        ];
    }
}