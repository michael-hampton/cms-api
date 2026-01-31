<?php

namespace App\Repositories\Offers;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Repositories\Repository;

class ProductOfferBundleRepository extends Repository
{
    public function getActiveBundles(): Collection
    {
        return ProductOfferBundle::active()
            ->with([
                'items.productOffer.product',
                'items.productOffer.merchant',
                'items.product',
                'items.product.merchant'
            ])
            ->published()
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function publish(int $id, int $userId): ?ProductOfferBundle
    {
        $bundle = $this->find($id);

        if (!$bundle || !$bundle->canBePublished()) {
            return null;
        }

        $bundle->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $userId,
        ]);

        return $this->findWithRelations($id);
    }

    public function update(int $id, array $data): ?ProductOfferBundle
    {
        $bundle = $this->find($id);

        if (!$bundle) {
            return null;
        }

        $updateData = array_filter([
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'total_price' => $data['total_price'] ?? null,
            'bundle_price' => $data['bundle_price'] ?? null,
            'discount_percentage' => $data['discount_percentage'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'is_active' => isset($data['is_active']) ? $data['is_active'] : null,
            'status' => $data['status'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
        ], fn($value) => $value !== null);

        $bundle->update($updateData);

        // Update items if provided
        if (isset($data['items']) && is_array($data['items'])) {
            // Delete existing items
            ProductOfferBundleItem::where('bundle_id', $bundle->id)->delete();

            // Add new items
            foreach ($data['items'] as $item) {
                ProductOfferBundleItem::create([
                    'bundle_id' => $bundle->id,
                    'product_offer_id' => $item['product_offer_id'] ?? null,
                    'product_id' => $item['product_id'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                ]);
            }
        }

        return $this->findWithRelations($id);
    }

    public function delete(int $id): bool
    {
        $bundle = ProductOfferBundle::find($id);

        if (!$bundle) {
            return false;
        }

        return $bundle->delete();
    }

    public function create(array $data): Model
    {
        $bundleData = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'total_price' => $data['total_price'],
            'bundle_price' => $data['bundle_price'],
            'discount_percentage' => $data['discount_percentage'] ?? 0,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $data['is_active'] ?? true,
            'status' => $data['status'] ?? 'pending',
            'created_by' => $data['created_by'] ?? null,
        ];

        $bundle = ProductOfferBundle::create($bundleData);

        // Add bundle items
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                ProductOfferBundleItem::create([
                    'bundle_id' => $bundle->id,
                    'product_offer_id' => $item['product_offer_id'] ?? null,
                    'product_id' => $item['product_id'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                ]);
            }
        }

        return $this->findWithRelations($bundle->id);
    }

    public function reject(int $id, int $userId, string $reason): ?ProductOfferBundle
    {
        $bundle = $this->find($id);

        if (!$bundle || $bundle->status !== 'pending') {
            return null;
        }

        $bundle->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $userId,
            'rejection_reason' => $reason,
        ]);

        return $this->findWithRelations($id);
    }

    public function getByStatus(string $status): Collection
    {
        return ProductOfferBundle::query()
            ->with([
                'items.productOffer.product',
                'items.productOffer.merchant',
                'items.product',
                'items.product.merchant'
            ])
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function findWithRelations(int $id): ?ProductOfferBundle
    {
        return ProductOfferBundle::query()
            ->with([
                'items.productOffer.product',
                'items.productOffer.merchant',
                'items.product',
                'items.product.merchant'
            ])
            ->find($id);
    }

    protected function getModelClass(): string
    {
        return ProductOfferBundle::class;
    }
}