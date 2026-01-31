<?php

namespace App\Services\Offers;

use App\Framework\Authorization\AuthenticationService;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundle;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use Exception;

class ProductOfferBundleService
{
    public bool $allowMultiMerchant;

    public function __construct(
        private readonly ProductOfferBundleRepository $repository,
        private readonly AuthenticationService  $authenticationService,
        private readonly ProductOfferRepository $offerRepository
    )
    {
        // Load from config
        $this->allowMultiMerchant = config('bundles.allow_multi_merchant', false);
    }

    public function getBundle(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function getActiveBundles(): Collection
    {
        return $this->repository->getActiveBundles();
    }

    public function createBundle(array $data): Model
    {
        $this->validateBundleDates($data['start_date'], $data['end_date']);
        $this->validateBundleItems($data['items'] ?? []);
        $this->validateMultiMerchant($data['items'] ?? []);
        $this->calculateBundlePricing($data);

        $data = $this->fillStatusFields($data);

        return $this->repository->create($data);
    }

    private function validateBundleDates(string $startDate, string $endDate): void
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

    private function validateBundleItems(array $items): void
    {
        if (empty($items)) {
            throw new Exception('Bundle must contain at least one item');
        }

        if (count($items) < 2) {
            throw new Exception('Bundle must contain at least two items');
        }

        foreach ($items as $item) {
            $hasProduct = !empty($item['product_id']);
            $hasOffer = !empty($item['product_offer_id']);

            if ($hasProduct && $hasOffer) {
                throw new Exception('Bundle item cannot have both product and product offer');
            }

            if (!$hasProduct && !$hasOffer) {
                throw new Exception('Bundle item must have either product or product offer');
            }
        }
    }

    private function validateMultiMerchant(array $items): void
    {
        if ($this->allowMultiMerchant) {
            return;
        }

        $merchantIds = [];

        foreach ($items as $item) {
            $merchantId = null;

            if (!empty($item['product_offer_id'])) {
                $offer = ProductOffer::find($item['product_offer_id']);
                $merchantId = $offer?->merchant_id;
            } elseif (!empty($item['product_id'])) {
                $product = Product::find($item['product_id']);
                $merchantId = $product?->merchants->first()->id;
            }

            if ($merchantId && !in_array($merchantId, $merchantIds)) {
                $merchantIds[] = $merchantId;
            }
        }

        if (count($merchantIds) > 1) {
            throw new Exception('Multi-merchant bundles are not allowed. Please enable in configuration or select items from the same merchant.');
        }
    }

    private function calculateBundlePricing(array &$data): void
    {
        $totalPrice = 0.0;

        foreach ($data['items'] ?? [] as $item) {
            $price = 0.0;
            $quantity = $item['quantity'] ?? 1;

            if (!empty($item['product_offer_id'])) {
                echo $item['product_offer_id'];
                $offer = $this->offerRepository->find($item['product_offer_id']);
                $price = $offer?->sale_price ?? 0;
            } elseif (!empty($item['product_id'])) {
                $product = Product::find($item['product_id']);
                $price = $product?->price ?? 0;
            }

            $totalPrice += $price * $quantity;
        }

        // Update total_price
        $data['total_price'] = $totalPrice;

        // Calculate discount if bundle_price is provided
        if (isset($data['bundle_price'])) {
            $savings = $totalPrice - $data['bundle_price'];
            $data['discount_percentage'] = $totalPrice > 0
                ? (int)round(($savings / $totalPrice) * 100)
                : 0;
        }
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

    public function updateBundle(int $id, array $data): ?ProductOfferBundle
    {
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $this->validateBundleDates($data['start_date'], $data['end_date']);
        }

        if (isset($data['items'])) {
            $this->validateBundleItems($data['items']);
            $this->validateMultiMerchant($data['items']);
        }

        if (isset($data['items']) || isset($data['bundle_price'])) {
            $this->calculateBundlePricing($data);
        }

        $currentBundle = $this->repository->find($id);
        if ($currentBundle && isset($data['status'])) {
            $data = $this->fillStatusFieldsOnUpdate($data, $currentBundle);
        }

        return $this->repository->update($id, $data);
    }

    private function fillStatusFieldsOnUpdate(array $data, ProductOfferBundle $currentBundle): array
    {
        if ($data['status'] === $currentBundle->status) {
            return $data;
        }

        $userId = $this->authenticationService->getUserId();
        if (!$userId) {
            return $data;
        }

        if ($data['status'] === 'published' && !$currentBundle->published_at) {
            $data['published_by'] = $userId;
            $data['published_at'] = now_datetime();
        } elseif ($data['status'] === 'rejected' && !$currentBundle->rejected_at) {
            $data['rejected_by'] = $userId;
            $data['rejected_at'] = now_datetime();
        }

        return $data;
    }

    public function deleteBundle(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function publish(int $id, int $userId): ?ProductOfferBundle
    {
        return $this->repository->publish($id, $userId);
    }

    public function reject(int $id, int $userId, string $reason): ?ProductOfferBundle
    {
        if (empty($reason)) {
            throw new Exception('Rejection reason is required');
        }

        return $this->repository->reject($id, $userId, $reason);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->repository->getByStatus($status);
    }

    public function isMultiMerchantAllowed(): bool
    {
        return $this->allowMultiMerchant;
    }

    /**
     * Get bundles with comprehensive search and filtering
     */
    public function getBundlesForWeb(array $filters): array
    {
        $query = ProductOfferBundle::with([
            'items.product',
            'items.product.merchant',
            'items.product.images',
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

        // Calculate savings for each bundle
        $bundles = $bundles->map(function ($bundle) {
            $bundle->savings = $bundle->total_price - $bundle->bundle_price;
            return $bundle;
        });

        return [
            'items' => $bundles->toArray(),
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'from' => (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $total)
            ]
        ];
    }
}