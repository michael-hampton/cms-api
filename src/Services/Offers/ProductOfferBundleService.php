<?php

namespace App\Services\Offers;

use App\Enums\Offers\BundleStatus;
use App\Exceptions\BundleValidationException;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Model;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundle;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;

class ProductOfferBundleService
{
    public bool $allowMultiMerchant;

    public function __construct(
        private readonly ProductOfferBundleRepository $repository,
        private readonly AuthenticationService  $authenticationService,
        private readonly ProductOfferRepository $offerRepository,
        private readonly ProductRepository      $productRepository,
        private readonly Database               $database
    )
    {
        // Load from config
        $this->allowMultiMerchant = config('bundles.allow_multi_merchant', false);
    }

    public function getBundle(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function getActiveBundles(int $limit = 10, ?Member $member = null, ?int $siteId = null): Collection
    {
        return $this->repository->getActiveBundles($member, $limit, $siteId);
    }

    public function createBundle(array $data): Model
    {
        $this->validateBundleDates($data['start_date'], $data['end_date']);
        $this->validateBundleItems($data['items'] ?? []);

        // Preload all entities to avoid N+1
        $entities = $this->preloadEntitiesForValidation($data['items'] ?? []);
        $this->validateMultiMerchant($data['items'] ?? [], $entities);

        $this->calculateBundlePricing($data, $entities);
        $data = $this->fillStatusFields($data);

        return $this->database->transaction(function () use ($data) {
            return $this->repository->create($data);
        });
    }


    private function validateBundleDates(string $startDate, string $endDate): void
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        if ($start === false || $end === false) {
            throw BundleValidationException::invalidDateFormat();
        }

        if ($end <= $start) {
            throw BundleValidationException::endDateBeforeStart();
        }
    }

    private function validateBundleItems(array $items): void
    {
        if (empty($items)) {
            throw BundleValidationException::emptyItems();
        }

        if (count($items) < 2) {
            throw BundleValidationException::insufficientItems();
        }

        foreach ($items as $item) {
            $hasProduct = !empty($item['product_id']);
            $hasOffer = !empty($item['product_offer_id']);

            if ($hasProduct && $hasOffer) {
                throw BundleValidationException::duplicateItemTypes();
            }

            if (!$hasProduct && !$hasOffer) {
                throw BundleValidationException::missingItemType();
            }
        }
    }

    /**
     * Preload all products and offers in a single query to avoid N+1
     */
    private function preloadEntitiesForValidation(array $items): array
    {
        $productIds = [];
        $offerIds = [];

        foreach ($items as $item) {
            if (!empty($item['product_id'])) {
                $productIds[] = $item['product_id'];
            }
            if (!empty($item['product_offer_id'])) {
                $offerIds[] = $item['product_offer_id'];
            }
        }

        $products = [];
        $offers = [];

        if (!empty($productIds)) {
            $products = $this->productRepository->findMany($productIds, ['merchants'])
                ->keyBy('id');
        }

        if (!empty($offerIds)) {
            $offers = ProductOffer::whereIn('id', array_unique($offerIds))
                ->get()
                ->keyBy('id');
        }

        return [
            'products' => $products,
            'offers' => $offers,
        ];
    }

    private function validateMultiMerchant(array $items, array $entities): void
    {
        if ($this->allowMultiMerchant) {
            return;
        }

        $merchantIds = [];

        foreach ($items as $item) {
            $merchantId = $this->extractMerchantId($item, $entities);

            if ($merchantId && !in_array($merchantId, $merchantIds, true)) {
                $merchantIds[] = $merchantId;
            }
        }

        if (count($merchantIds) > 1) {
            throw BundleValidationException::multiMerchantNotAllowed();
        }
    }

    private function extractMerchantId(array $item, array $entities): ?int
    {
        if (!empty($item['product_offer_id'])) {
            $offer = $entities['offers']->get($item['product_offer_id']);
            return $offer?->merchant_id;
        }

        if (!empty($item['product_id'])) {
            $product = $entities['products']->get($item['product_id']);

            return $product?->merchants?->first()?->id;
        }

        return null;
    }

    private function calculateBundlePricing(array &$data, array $entities): void
    {
        $totalPrice = 0.0;

        foreach ($data['items'] ?? [] as $item) {
            $price = 0.0;
            $quantity = $item['quantity'] ?? 1;

            if (!empty($item['product_offer_id'])) {
                $offer = $entities['offers']->get($item['product_offer_id']);
                $price = $offer?->sale_price ?? 0;
            } elseif (!empty($item['product_id'])) {
                $product = $entities['products']->get($item['product_id']);
                $price = $product?->price ?? 0;
            }

            $totalPrice += $price * $quantity;
        }

        $data['total_price'] = $totalPrice;

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

        $status = BundleStatus::from($data['status']);
        $userId = $this->authenticationService->getUserId();

        if (!$userId) {
            return $data;
        }

        if ($status === BundleStatus::PUBLISHED) {
            $data['published_by'] = $userId;
            $data['published_at'] = now_datetime();
        } elseif ($status === BundleStatus::REJECTED) {
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

        $entities = [];
        if (isset($data['items'])) {
            $this->validateBundleItems($data['items']);
            $entities = $this->preloadEntitiesForValidation($data['items']);
            $this->validateMultiMerchant($data['items'], $entities);
        }

        if (isset($data['items']) || isset($data['bundle_price'])) {
            if (empty($entities) && isset($data['items'])) {
                $entities = $this->preloadEntitiesForValidation($data['items']);
            }
            $this->calculateBundlePricing($data, $entities);
        }

        return $this->database->transaction(function () use ($id, $data) {
            $currentBundle = $this->repository->find($id);

            if ($currentBundle && isset($data['status'])) {
                $data = $this->fillStatusFieldsOnUpdate($data, $currentBundle);
            }

            return $this->repository->update($id, $data);
        });
    }

    private function fillStatusFieldsOnUpdate(array $data, ProductOfferBundle $currentBundle): array
    {
        if ($data['status'] === $currentBundle->status) {
            return $data;
        }

        $status = BundleStatus::from($data['status']);
        $userId = $this->authenticationService->getUserId();

        if (!$userId) {
            return $data;
        }

        if ($status === BundleStatus::PUBLISHED && !$currentBundle->published_at) {
            $data['published_by'] = $userId;
            $data['published_at'] = now_datetime();
        } elseif ($status === BundleStatus::REJECTED && !$currentBundle->rejected_at) {
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
            throw BundleValidationException::rejectionReasonRequired();
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