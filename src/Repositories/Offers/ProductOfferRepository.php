<?php

namespace App\Repositories\Offers;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\OfferClicks;
use App\Models\ProductOffer;
use App\Repositories\Contracts\TrackableRepository;
use App\Repositories\Repository;

class ProductOfferRepository extends Repository implements TrackableRepository
{
    public function getActiveOffersForProduct(int $productId): Collection
    {
        return ProductOffer::forProduct($productId)
            ->with(['merchant', 'product'])
            ->active()
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getActiveOffersForCategory(int $categoryId): Collection
    {
        return ProductOffer::forCategory($categoryId)
            ->with(['product', 'merchant'])
            ->active()
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function create(array $data): Model
    {
        // If this offer is active, deactivate other offers for this product
        if ($data['is_active'] ?? true) {
            $this->deactivateOtherOffers($data['product_id']);
        }

        return ProductOffer::create($data);
    }

    public function deactivateOtherOffers(int $productId, ?int $excludeOfferId = null): void
    {
        $query = ProductOffer::where('product_id', $productId)
            ->active();

        if ($excludeOfferId) {
            $query->where('id', '!=', $excludeOfferId);
        }

        $query->update(['is_active' => 0]);
    }

    public function update(int $id, array $data): ?ProductOffer
    {
        $offer = $this->find($id);

        if (!$offer) {
            return null;
        }

        // If activating this offer, deactivate others
        if (isset($data['is_active']) && $data['is_active']) {
            $this->deactivateOtherOffers($offer->product_id, $id);
        }

        $offer->update($data);

        return ProductOffer::with(['product', 'merchant'])->find($id);
    }

    public function delete(int $id): bool
    {
        $offer = ProductOffer::find($id);

        if (!$offer) {
            return false;
        }

        return $offer->delete();
    }

    public function hasActiveOffer(int $productId): bool
    {
        return ProductOffer::forProduct($productId)
            ->active()
            ->exists();
    }

    public function getByStatus(string $status): Collection
    {
        return ProductOffer::with(['product', 'merchant', 'voucher'])
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function publish(int $id, int $userId): ?ProductOffer
    {
        $offer = $this->find($id);

        if (!$offer || !$offer->canBePublished()) {
            return null;
        }

        $offer->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $userId,
        ]);

        return $offer->fresh(['product', 'merchant', 'voucher']);
    }

    public function reject(int $id, int $userId, string $reason): ?ProductOffer
    {
        $offer = $this->find($id);

        if (!$offer || $offer->status !== 'pending') {
            return null;
        }

        $offer->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $userId,
            'rejection_reason' => $reason,
        ]);

        return $offer->fresh(['product', 'merchant', 'voucher']);
    }

    public function search(array $filters): Collection
    {
        $query = ProductOffer::with(['product', 'merchant', 'voucher']);

        if (!empty($filters['merchant_id'])) {
            $query->where('merchant_id', $filters['merchant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['voucher_id'])) {
            $query->where('voucher_id', $filters['voucher_id']);
        }

        if (!empty($filters['search'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function trackClick(
        int     $offerId,
        ?int    $memberId,
        string  $action,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array   $metadata = []  // ADD THIS
    ): Model
    {
        return OfferClicks::create([
            'offer_id' => $offerId,
            'member_id' => $memberId,
            'action' => $action,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'channel' => $metadata['channel'] ?? null,
            'surface_type' => $metadata['surface_type'] ?? null,
            'surface_id' => $metadata['surface_id'] ?? null,
            'deal_id' => $metadata['deal_id'] ?? null,
            'clicked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getClickStatistics(array $offerIds): array
    {
        if (empty($offerIds)) {
            return [
                'total' => 0,
                'unique' => 0,
                'by_offer' => [],
                'by_action' => []
            ];
        }

        $totalClicks = OfferClicks::whereIn('offer_id', $offerIds)->count();

        $uniqueClickers = OfferClicks::whereIn('offer_id', $offerIds)
            ->whereNotNull('member_id')
            ->select('member_id')
            ->distinct()
            ->count();

        $clicksByOffer = OfferClicks::whereIn('offer_id', $offerIds)
            ->select('offer_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('offer_id')
            ->get()
            ->pluck('count', 'offer_id')  // First param is value, second is key
            ->toArray();

        $clicksByAction = OfferClicks::whereIn('offer_id', $offerIds)
            ->select('action')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action')
            ->toArray();

        return [
            'total' => $totalClicks,
            'unique' => $uniqueClickers,
            'by_offer' => $clicksByOffer,
            'by_action' => $clicksByAction
        ];
    }

    public function searchOffersWithFilters(array $filters): array
    {
        $query = ProductOffer::with(['product', 'product.images', 'merchant', 'product.category']);

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Search by product name or merchant name
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'LIKE', "%{$search}%");
                })
                    ->orWhereHas('merchant', function ($merchantQuery) use ($search) {
                        $merchantQuery->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Category filter
        if (!empty($filters['category'])) {
            $query->whereHas('product', function ($productQuery) use ($filters) {
                $productQuery->where('category', $filters['category']);
            });
        }

        // Merchant filter
        if (!empty($filters['merchant_id'])) {
            $query->where('merchant_id', $filters['merchant_id']);
        }

        // Minimum discount filter
        if (!empty($filters['min_discount'])) {
            $query->whereRaw(
                'original_price > 0 AND ((original_price - sale_price) / original_price) * 100 >= :min_discount',
                ['min_discount' => $filters['min_discount']]
            );
        }

        // Price range filter
        if (!empty($filters['min_price'])) {
            $query->where('sale_price', '>=', $filters['min_price']);
        }
        if (!empty($filters['max_price'])) {
            $query->where('sale_price', '<=', $filters['max_price']);
        }

        // Featured filter
        if (!empty($filters['is_featured'])) {
            $isFeatured = filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_featured', $isFeatured);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSortFields = [
            'created_at', 'sale_price', 'original_price',
            'discount_percentage', 'start_date', 'end_date'
        ];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($filters['per_page'] ?? 20, 100); // Max 100 per page
        $page = $filters['page'] ?? 1;

        //dd($query->toSql());

        $total = $query->count();
        $offers = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $offers,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ];
    }

    public function getActiveOffers(): Collection
    {
        return ProductOffer::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByProductAndMerchant(int $productId, int $merchantId): ?ProductOffer
    {
        return ProductOffer::where('product_id', $productId)
            ->where('merchant_id', $merchantId)
            ->first();
    }

    protected function getModelClass(): string
    {
        return ProductOffer::class;
    }

    public function hasTracked(
        int    $entityId,
        int    $memberId,
        string $action,
        string $surfaceType,
        int    $surfaceId,
    ): bool
    {
        return OfferClicks::where('offer_id', $entityId)
            ->where('member_id', $memberId)
            ->where('action', $action)
            ->where('surface_type', $surfaceType)
            ->where('surface_id', $surfaceId)
            ->exists();
    }
}