<?php

namespace App\Repositories\Product;

use App\Enums\Boost\BoostContext;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Framework\Support\Str;
use App\Models\Block;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\Model;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImpression;
use App\Models\ProductMerchant;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundleItem;
use App\Models\ProductPriceHistory;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;
use App\Services\Adverts\Boost\BoostRankingService;

class ProductRepository extends Repository implements ProductRepositoryInterface
{
    private SearchEngine $searchEngine;

    public function __construct(
        private readonly ProductSpecificationGroupRepository $specificationGroupRepository,
        private readonly BoostRankingService                 $boostRankingService
    )
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('product');
        $this->searchEngine = new SearchEngine($config);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Search products with optional member-scoped region visibility.
     *
     * When a Member is supplied, the scopeVisibleToMember constraint is applied
     * so that products restricted to region sets the member's territory does not
     * belong to are excluded from results.
     */
    public function search(SearchCriteria $criteria, ?Member $member = null): PaginatedResult
    {
        $boostedIds = [];
        try {
            $boostedIds = $this->boostRankingService->getActiveBoostedIds(BoostContext::Listing->value);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch boosted IDs for listing', ['error' => $e->getMessage()]);
        }

        $query = Product::with([
            'activeVariants',
            'availableMerchants',
            'images',
            'specifications',
            'priceHistory',
            'activeVariants.images',
            'availableMerchants.merchant',
            'brand',
            'category',
            'approvedReviews',
            'regionSets',
        ]);

        // Apply member-scoped region visibility. When no member is given (e.g.
        // admin contexts), the scope is a no-op and all products are returned.
        $query->visibleToMember($member);

        if (!empty($boostedIds)) {
            $ids = implode(',', array_map('intval', $boostedIds));
            $query->orderByRaw("CASE WHEN id IN ({$ids}) THEN 0 ELSE 1 END");
        }

        return $this->searchEngine->search($query, $criteria);
    }

    public function all(): Collection
    {
        return $this->model->latest()->get();
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        return $this->model->latest()->paginate($perPage);
    }

    public function findByCategory(string $category): Collection
    {
        return $this->model->byCategory($category)->latest()->get();
    }

    public function findByBrand(string $brand): Collection
    {
        return $this->model->byBrand($brand)->latest()->get();
    }

    public function getOnSale(): Collection
    {
        return $this->model->onSale()->latest()->get();
    }

    public function findBySlug(string $slug): ?Product
    {
        return Product::bySlug($slug)->first();
    }

    public function getActiveSaleProducts(int $siteId): Collection
    {
        return Product::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->whereRaw('sale_price < price')
            ->get();
    }

    public function getBundlesForProduct(int $productId): Collection
    {
        return ProductOfferBundleItem::with(['bundle'])
            ->where('product_id', $productId)
            ->get();
    }

    public function getActiveOffersForProduct(int $productId): Collection
    {
        return ProductOffer::forProduct($productId)
            ->with(['merchant', 'product'])
            ->active()
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function lockForUpdate(int $id): ?Model
    {
        return $this->find($id);
    }

    public function totalImpressionsForMerchant(int $id, ?int $monthsAgo = null)
    {
        $query = ProductImpression::query()
            ->join('products', 'products.id', '=', 'product_impressions.product_id')
            ->join('product_merchants', 'product_merchants.product_id', '=', 'products.id')
            ->where('product_merchants.merchant_id', $id);

        if ($monthsAgo !== null) {
            $query->where(
                'product_impressions.viewed_at',
                '>=',
                now_datetime()->subMonths($monthsAgo)->startOfMonth()->format('Y-m-d')
            );
        }

        return $query->count();
    }

    public function topByRevenueForMerchant(int $id, int $limit = 10)
    {
        $rows = OrderItem::query()
            ->from('oi')
            ->selectRaw('
            p.id as product_id,
            p.name,
            SUM(oi.quantity * oi.unit_price) as revenue
        ')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->join('product_merchants as pm', 'pm.product_id', '=', 'p.id')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('pm.merchant_id', $id)
            ->where('o.status', 'completed')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $productIds = $rows->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return $rows->map(function ($row) use ($products) {
            $product = $products->get($row['product_id']) ?? null;

            if ($product) {
                $product->setAttribute('revenue', (float)$row['revenue']);
            }

            return $product;
        })->filter();
    }

    public function findRelated(Product $product, int $limit = 8): Collection
    {
        return $this->model
            ->where('id', '!=', $product->id)
            ->active()
            ->where(function ($query) use ($product) {
                $query->where('category_id', $product->category_id)
                    ->orWhere('brand_id', $product->brand_id);
            })
            ->limit($limit)
            ->latest()
            ->get();
    }

    public function getActiveProducts(array $productIds, int $limit = 6): Collection
    {
        if (empty($productIds)) {
            return new Collection([]);
        }

        return $this->model
            ->whereIn('id', $productIds)
            ->active()
            ->limit($limit)
            ->get();
    }

    public function syncImages(int $productId, array $images): void
    {
        ProductImage::where('product_id', $productId)->whereNull('variant_id')->delete();

        foreach ($images as $imageData) {
            ProductImage::create([
                'product_id' => $productId,
                'url' => $imageData['url'],
                'alt' => $imageData['alt'] ?? null,
                'is_primary' => $imageData['is_primary'] ?? false,
                'sort_order' => $imageData['sort_order'] ?? 0,
                'variant_id' => null
            ]);
        }
    }

    public function delete(int $id): bool
    {
        $product = $this->find($id);

        if (!$product) {
            return false;
        }

        return $product->delete();
    }

    public function getImages(int $productId): Collection
    {
        return ProductImage::where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }

    public function syncMerchants(int $productId, array $merchants): array
    {
        $existingMerchants = $this->getFormattedMerchants($productId);

        $merchantIds = [];

        foreach ($merchants as $merchantData) {
            $merchantLookup = $this->findOrCreateMerchant($merchantData['name']);

            $existingPM = null;
            if (!empty($merchantData['id']) && $existingMerchants->has($merchantData['id'])) {
                $existingPM = $existingMerchants->get($merchantData['id']);
            } else {
                $query = ProductMerchant::where('product_id', $productId)
                    ->where('merchant_id', $merchantLookup->id);

                if (isset($merchantData['variant_id'])) {
                    $query->where('variant_id', $merchantData['variant_id']);
                } else {
                    $query->whereNull('variant_id');
                }

                $existingPM = $query->first()?->toArray();
            }

            $updateData = [
                'url' => $merchantData['url'],
                'is_available' => $merchantData['is_available'] ?? true,
                'variant_id' => $merchantData['variant_id'] ?? null,
                'variant_sku' => $merchantData['variant_sku'] ?? null,
                'override_price' => !empty($merchantData['override_price']) ? $merchantData['override_price'] : 0,
                'override_sale_price' => !empty($merchantData['override_sale_price']) ? $merchantData['override_sale_price'] : 0,
                'price' => $merchantData['price'] ?? 0,
                'sale_price' => $merchantData['sale_price'] ?? null,
            ];

            if ($existingPM) {
                ProductMerchant::where('id', $existingPM['id'])->update($updateData);
                $merchantIds[] = $existingPM['id'];
            } else {
                $productMerchant = ProductMerchant::create(array_merge([
                    'product_id' => $productId,
                    'merchant_id' => $merchantLookup->id,
                    'last_price_check' => now(),
                ], $updateData));
                $merchantIds[] = $productMerchant->id;
            }
        }

        return $merchantIds;
    }

    private function getFormattedMerchants(int $productId)
    {
        $existingMerchants = ProductMerchant::with(['merchant'])->where('product_id', $productId)
            ->get()->toArray();

        $formattedMerchants = [];

        foreach ($existingMerchants as $existingMerchant) {
            $formattedMerchants[$existingMerchant['merchant']['id']] = $existingMerchant;
        }

        return collect($formattedMerchants);
    }

    protected function findOrCreateMerchant(string $name): Merchant
    {
        $merchant = Merchant::where('name', $name)->first();

        if (!$merchant) {
            $merchant = Merchant::create(['name' => $name, 'slug' => Str::slug($name)]);
        }

        return $merchant;
    }

    public function getMerchants(int $productId): Collection
    {
        return $this->getProductMerchantsWithDetails($productId);
    }

    public function getProductMerchantsWithDetails(int $productId): Collection
    {
        return ProductMerchant::with(['merchant', 'variant'])
            ->where('product_id', $productId)
            ->get()
            ->map(function ($pm) {
                return [
                    'id' => $pm->id,
                    'merchant_id' => $pm->merchant_id,
                    'name' => $pm->merchant->name,
                    'url' => $pm->url,
                    'price' => $pm->price,
                    'sale_price' => $pm->sale_price,
                    'override_price' => $pm->override_price,
                    'override_sale_price' => $pm->override_sale_price,
                    'variant_sku' => $pm->variant_sku,
                    'is_available' => $pm->is_available,
                    'variant_id' => $pm->variant_id,
                    'variant' => $pm->variant ? [
                        'id' => $pm->variant->id,
                        'sku' => $pm->variant->sku,
                        'name' => $pm->variant->name,
                        'price' => $pm->variant->price,
                        'sale_price' => $pm->variant->sale_price,
                        'attributes' => $pm->variant->attributes,
                    ] : null,
                    'effective_price' => $pm->effective_price,
                    'effective_sale_price' => $pm->effective_sale_price,
                    'effective_sku' => $pm->effective_sku,
                    'discount_percentage' => $pm->discount_percentage,
                    'has_discount' => $pm->has_discount,
                    'final_price' => $pm->final_price,
                    'last_price_check' => $pm->last_price_check,
                ];
            });
    }

    public function recordMerchantPriceHistory(int $productId, int $productMerchantId, float $price, int $merchantId, ?float $salePrice = null): ?Model
    {
        if ($price < 0) {
            return null;
        }

        return ProductPriceHistory::create([
            'product_id' => $productId,
            'product_merchant_id' => $productMerchantId,
            'merchant_id' => $merchantId,
            'price' => $price,
            'sale_price' => $salePrice,
            'recorded_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getPriceHistory(int $productId, ?int $merchantId = null): Collection
    {
        $query = ProductPriceHistory::with(['merchant'])->where('product_id', $productId);

        if ($merchantId !== null) {
            $query->where('product_merchant_id', $merchantId);
        }

        return $query->orderBy('recorded_at', 'desc')->get();
    }

    public function deleteMerchants(int $productId): void
    {
        ProductMerchant::where('product_id', $productId)->delete();
    }

    public function syncVariants(int $productId, array $variants): array
    {
        ProductVariant::where('product_id', $productId)->delete();

        $variantIds = [];
        foreach ($variants as $variantData) {
            $images = $variantData['images'] ?? [];
            $regionSetIds = $variantData['region_set_ids'] ?? [];
            unset($variantData['images']);

            $variant = ProductVariant::create([
                'product_id' => $productId,
                'sku' => $variantData['sku'],
                'name' => $variantData['name'] ?? null,
                'attributes' => $variantData['attributes'] ?? [],
                'price' => $variantData['price'] ?? 0,
                'sale_price' => $variantData['sale_price'] ?? null,
                'price_modifier' => $variantData['price_modifier'] ?? 0,
                'is_active' => $variantData['is_active'] ?? true,
            ]);

            $variantIds[] = $variant->id;

            if (!empty($images)) {
                $this->syncVariantImages($variant->id, $productId, $images);
            }

            if (!empty($regionSetIds)) {
                $this->syncVariantRegionSets($variant->id, $regionSetIds);
            }
        }

        return $variantIds;
    }

    public function syncVariantImages(int $variantId, int $productId, array $images): void
    {
        ProductImage::where('variant_id', $variantId)->delete();

        foreach ($images as $imageData) {
            ProductImage::create([
                'product_id' => $productId,
                'variant_id' => $variantId,
                'url' => $imageData['url'],
                'alt' => $imageData['alt'] ?? null,
                'is_primary' => $imageData['is_primary'] ?? false,
                'sort_order' => !empty($imageData['sort_order']) && is_numeric($imageData['sort_order']) ? $imageData['sort_order'] : 0,
            ]);
        }
    }

    /**
     * Sync a variant's region set associations.
     * Passing an empty array removes all restrictions (globally visible).
     *
     * @param int $variantId
     * @param int[] $regionSetIds
     */
    public function syncVariantRegionSets(int $variantId, array $regionSetIds): void
    {
        $variant = ProductVariant::find($variantId);

        if (!$variant) {
            return;
        }

        $variant->regionSets(true)->sync($regionSetIds);
    }

    public function getVariantImages(int $variantId): Collection
    {
        return ProductImage::where('variant_id', $variantId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getVariants(int $productId): Collection
    {
        return ProductVariant::with(['images'])->where('product_id', $productId)->get();
    }

    public function getVariantById(int $variantId): ?Model
    {
        return ProductVariant::with(['images'])->where('id', $variantId)->first();
    }

    public function deleteVariants(int $productId): void
    {
        ProductVariant::where('product_id', $productId)->delete();
    }

    public function syncSpecifications(int $productId, array $specifications): void
    {
        ProductSpecification::where('product_id', $productId)->delete();

        foreach ($specifications as $specData) {
            $groupId = null;
            if (!empty($specData['category'])) {
                $group = $this->specificationGroupRepository
                    ->findOrCreateByName($specData['category']);
                $groupId = $group->id;
            }

            ProductSpecification::create([
                'product_id' => $productId,
                'specification_group_id' => $groupId,
                'category' => $specData['category'] ?? 'General',
                'key' => $specData['key'],
                'value' => $specData['value'],
                'sort_order' => $specData['sort_order'] ?? 0,
            ]);
        }
    }

    public function getSpecifications(int $productId): Collection
    {
        return ProductSpecification::where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }

    public function deleteSpecifications(int $productId): void
    {
        ProductSpecification::where('product_id', $productId)->delete();
    }

    public function recordPriceHistory(Product $product): ?Model
    {
        if ($product->price < 0 && $product->sale_price < 0) {
            return null;
        }

        return ProductPriceHistory::create([
            'product_id' => $product->id,
            'product_merchant_id' => null,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'recorded_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function deletePriceHistory(int $productId): void
    {
        ProductPriceHistory::where('product_id', $productId)->delete();
    }

    public function findBySlugAndSite(string $slug, int $siteId): ?Product
    {
        return Product::where('slug', $slug)
            ->where('site_id', $siteId)
            ->first();
    }

    public function updateVariant(int $variantId, array $data): bool
    {
        $variant = ProductVariant::find($variantId);

        if (!$variant) {
            return false;
        }

        $variant->update($data);
        return true;
    }

    public function deleteVariant(int $variantId): bool
    {
        $variant = ProductVariant::find($variantId);

        if (!$variant) {
            return false;
        }

        $this->deleteVariantImages($variantId);

        return $variant->delete();
    }

    public function deleteVariantImages(int $variantId): void
    {
        ProductImage::where('variant_id', $variantId)->delete();
    }

    public function getAllMerchantLookups(): Collection
    {
        return Merchant::orderBy('name')->get();
    }

    public function searchByName(string $name, ?int $siteId, int $limit = 10): Collection
    {
        return Product::active()
            ->when($siteId, function ($query) use ($siteId) {
                $query->where('site_id', $siteId);
            })
            ->where(function ($query) use ($name) {
                $query->where('name', 'LIKE', "%{$name}%")
                    ->orWhere('name', 'LIKE', "%" . str_replace(' ', '%', $name) . "%");
            })
            ->limit($limit)
            ->get();
    }

    public function getProductPages(int $productId): Collection
    {
        $blocks = Block::whereIn('type', [
            'product',
            'deal',
            'product-comparison',
        ])->get();

        $filtered = $blocks->filter(function (Block $block) use ($productId) {
            $data = $block->data ?? [];

            return match ($block->type) {
                'product' => ($data['product_id'] ?? null) == $productId,
                'deal' => ($data['product_id'] ?? null) == $productId,
                'product-comparison' =>
                    ($data['product_a_id'] ?? null) == $productId ||
                    ($data['product_b_id'] ?? null) == $productId,
                default => false,
            };
        });

        $pageIds = $filtered->pluck('page_id')->unique()->toArray();

        return Page::whereIn('id', $pageIds)
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    public function getProductCardData($productId): array
    {
        $product = $this->find($productId);

        if (!$product) {
            return [];
        }

        $topReview = \App\Models\Review::where('product_id', $productId)
            ->where('is_approved', true)
            ->orderBy('rating', 'desc')
            ->orderBy('helpful_count', 'desc')
            ->first();

        $merchants = \App\Models\ProductMerchant::where('product_id', $productId)
            ->where('is_available', true)
            ->with(['merchant'])
            ->orderBy('price', 'asc')
            ->get();

        return [
            'top_review' => $topReview ? [
                'rating' => $topReview->rating,
                'title' => $topReview->title,
                'comment' => substr($topReview->comment, 0, 100) . '...',
                'author' => $topReview->author_name,
                'is_verified' => $topReview->is_verified_purchase
            ] : null,
            'merchants' => $merchants->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->merchant->name,
                'price' => $m->effective_price,
                'sale_price' => $m->effective_sale_price,
                'url' => $m->url,
                'is_available' => $m->is_available
            ])->toArray(),
            'merchant_count' => $merchants->count(),
            'lowest_price' => $merchants->min('effective_sale_price') ?? $merchants->min('effective_price')
        ];
    }

    public function getRecommendationProducts(int $siteId, int $limit = 10, array $foundProductIds = []): Collection
    {
        $popularQuery = Product::where('site_id', $siteId)
            ->active();

        if (!empty($foundProductIds)) {
            $popularQuery->whereNotIn('id', $foundProductIds);
        }

        $popularQuery->orderBy('created_at', 'desc')
            ->limit($limit);

        return $popularQuery->get();
    }

    public function getRecentlyViewed(array $productIds, int $limit = 6): Collection
    {
        if (empty($productIds)) {
            return new Collection([]);
        }

        return Product::whereIn('id', $productIds)
            ->active()
            ->with(['images', 'brand', 'approvedReviews'])
            ->limit($limit)
            ->get();
    }

    public function findProductMerchant(int $productId, int $merchantId): ?Model
    {
        return ProductMerchant::where('product_id', $productId)
            ->where('merchant_id', $merchantId)
            ->first();
    }

    public function updateProductMerchant(int $id, array $data): bool
    {
        return ProductMerchant::where('id', $id)->update($data);
    }

    public function createProductMerchant(int $productId, array $data): Model
    {
        return ProductMerchant::create(array_merge(['product_id' => $productId], $data));
    }

    public function findBySku(string $sku): ?Model
    {
        return Product::where('sku', $sku)->first();
    }

    public function getProductsByMerchant(int $merchantId): Collection
    {
        return Product::with(['brand', 'category', 'images'])
            ->whereHas('merchants', function ($query) use ($merchantId) {
                $query->where('merchant_id', $merchantId);
            })
            ->active()
            ->get()
            ->map(function ($product) use ($merchantId) {
                $merchantData = $product->merchants->firstWhere('merchant_id', $merchantId);
                $product->merchant_data = $merchantData;
                return $product;
            });
    }

    public function findWithRelations(int $productId, array $relations = [])
    {
        return Product::with($relations)->find($productId);
    }

    /**
     * Sync a product's region set associations.
     * Passing an empty array removes all restrictions (globally visible).
     *
     * @param int $productId
     * @param int[] $regionSetIds
     */
    public function syncRegionSets(int $productId, array $regionSetIds): void
    {
        $product = $this->find($productId);

        if (!$product) {
            return;
        }

        $product->regionSets(true)->sync($regionSetIds);
    }

    protected function getModelClass(): string
    {
        return Product::class;
    }
}