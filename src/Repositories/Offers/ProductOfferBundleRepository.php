<?php

namespace App\Repositories\Offers;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Model;
use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Repositories\Repository;

class ProductOfferBundleRepository extends Repository
{
    public function getActiveBundles(?Member $member = null, int $limit = 10, ?int $siteId = null): Collection
    {
        return ProductOfferBundle::active()
            ->when($siteId, function ($query) use ($siteId) {
                $query->where('site_id', $siteId);
            })
            ->with([
                'items.productOffer.product',
                'items.productOffer.merchant',
                'items.product',
                'items.product.merchant'
            ])
            ->when($member, function ($query) use ($member) {
                $query->visibleToMember($member);
            })
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
            'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
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
            'site_id' => $data['site_id'],
            'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
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

    public function searchBundles(array $filters): array
    {
        $query = ProductOfferBundle::with([
            'items',
            'items.productOffer.product',
            'items.productOffer.product.images',
            'items.productOffer.merchant'
        ]);

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Search by bundle name or product names within bundle
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhereHas('items', function ($itemQuery) use ($search) {
                        $itemQuery->whereHas('product', function ($prodQuery) use ($search) {
                            $prodQuery->where('name', 'LIKE', "%{$search}%");
                        })
                            ->orWhereHas('productOffer.product', function ($prodQuery) use ($search) {
                                $prodQuery->where('name', 'LIKE', "%{$search}%");
                            });
                    });
            });
        }

        // Category filter (bundles containing products from category)
        if (!empty($filters['category'])) {
            $query->whereHas('items', function ($itemQuery) use ($filters) {
                $itemQuery->whereHas('product', function ($prodQuery) use ($filters) {
                    $prodQuery->where('category', $filters['category']);
                })
                    ->orWhereHas('productOffer.product', function ($prodQuery) use ($filters) {
                        $prodQuery->where('category', $filters['category']);
                    });
            });
        }

        // Minimum savings filter
        if (!empty($filters['min_savings'])) {
            $query->whereRaw('(total_price - bundle_price) >= ?', [$filters['min_savings']]);
        }

        // Price range filter
        if (!empty($filters['min_price'])) {
            $query->where('bundle_price', '>=', $filters['min_price']);
        }
        if (!empty($filters['max_price'])) {
            $query->where('bundle_price', '<=', $filters['max_price']);
        }

        // Discount percentage filter
        if (!empty($filters['min_discount'])) {
            $query->where('discount_percentage', '>=', $filters['min_discount']);
        }

        // Date range filter
        if (!empty($filters['start_date'])) {
            $query->where('start_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('end_date', '<=', $filters['end_date']);
        }

        // Multi-merchant filter
        if (!empty($filters['merchant_type'])) {
            if ($filters['merchant_type'] === 'single') {
                $query->whereHas('items', function ($itemQuery) {
                    // This would need a more complex query to ensure all items have same merchant
                    // For now, simplified version
                });
            }
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSortFields = [
            'created_at', 'bundle_price', 'total_price',
            'discount_percentage', 'start_date', 'end_date', 'name'
        ];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Special sorting for savings
        if ($sortBy === 'savings') {
            $query->orderByRaw('(total_price - bundle_price) ' . strtoupper($sortOrder));
        }

        // Pagination
        $perPage = min($filters['per_page'] ?? 20, 100); // Max 100 per page
        $page = $filters['page'] ?? 1;

        $total = $query->count();
        $bundles = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $bundles,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ];
    }

    protected function getModelClass(): string
    {
        return ProductOfferBundle::class;
    }
}