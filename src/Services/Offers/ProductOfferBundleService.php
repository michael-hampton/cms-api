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
        $bundleData = $this->repository->searchBundles($filters);
        $bundles = $bundleData['data'];


        // Calculate savings for each bundle
        $bundles = $bundles->map(function ($bundle) {
            $bundle->savings = $bundle->total_price - $bundle->bundle_price;
            return $bundle;
        });

        foreach ($bundles as $bundle) {
            $items = $bundle->items;

            $products = Product::with(['category', 'merchants', 'merchants.merchant', 'images'])
                ->whereIn('id', $items->pluck('product_id')->unique())
                ->get()
                ->keyBy('id');

            $bundle->items = $items->map(function ($item) use ($products) {
                $item->product = $products->get($item->product_id)->toArray();
                return $item;
            });
        }

        return [
            'items' => $bundles->toArray(),
            'pagination' => [
                'current_page' => (int)$bundleData['page'],
                'per_page' => (int)$bundleData['per_page'],
                'total' => $bundleData['total'],
                'total_pages' => ceil($bundleData['total'] / $bundleData['per_page']),
                'from' => (($bundleData['page'] - 1) * $bundleData['per_page']) + 1,
                'to' => min($bundleData['page'] * $bundleData['per_page'], $bundleData['total'])
            ]
        ];
    }

    public function getActiveBundlesForWeb(int $limit = 10): array
    {
        $bundles = $this->repository->getActiveBundles()->take($limit);

        return $bundles->map(function ($bundle) {
            $items = $bundle->items;

            // Get merchant info from bundle items
            $merchants = [];
            foreach ($items as $item) {
                $merchant = $item->getEffectiveMerchant();
                if ($merchant && !isset($merchants[$merchant->id])) {
                    $merchants[$merchant->id] = $merchant->name;
                }
            }

            $isMultiMerchant = count($merchants) > 1;

            // Get image from first product
            $firstProduct = $items->first()?->getEffectiveProduct();

            return [
                'bundle_id' => $bundle->id,
                'name' => $bundle->name,
                'slug' => $bundle->slug,
                'description' => $bundle->description,
                'image' => $firstProduct?->main_image_url,
                'total_price' => $bundle->total_price,
                'bundle_price' => $bundle->bundle_price,
                'savings' => $bundle->calculateSavings(),
                'discount_percentage' => $bundle->discount_percentage,
                'item_count' => $items->count(),
                'is_multi_merchant' => $isMultiMerchant,
                'merchants' => array_values($merchants),
                'in_stock' => $this->checkBundleStock($items),
            ];
        })->toArray();
    }

    private function checkBundleStock($items): bool
    {
        foreach ($items as $item) {
            $product = $item->getEffectiveProduct();
            if (!$product || !($product->in_stock ?? true)) {
                return false;
            }
        }
        return true;
    }
}