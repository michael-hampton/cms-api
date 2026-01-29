<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\ProductOffer;
use App\Repositories\Repository;

class ProductOfferRepository extends Repository
{
    public function getActiveOffersForProduct(int $productId): Collection
    {
        return ProductOffer::forProduct($productId)
            ->with(['merchant'])
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

    protected function getModelClass(): string
    {
        return ProductOffer::class;
    }
}