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
            ->where('is_active', true)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getActiveOffersForCategory(int $categoryId): Collection
    {
        return ProductOffer::forCategory($categoryId)
            ->with(['product', 'merchant'])
            ->where('is_active', true)
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
            ->where('is_active', true);

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
            ->where('is_active', true)
            ->exists();
    }

    protected function getModelClass(): string
    {
        return ProductOffer::class;
    }
}