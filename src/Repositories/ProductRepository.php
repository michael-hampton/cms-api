<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class ProductRepository extends Repository implements ProductRepositoryInterface
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::createProductConfiguration();
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Product::query();
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

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function delete(int $id): bool
    {
        $product = $this->find($id);

        if (!$product) {
            return false;
        }

        return $product->delete();
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


    protected function getModelClass(): string
    {
       return Product::class;
    }

    public function findRelated(Product $product, int $limit = 8): Collection
    {
        return $this->model
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->where(function($query) use ($product) {
                $query->where('category_id', $product->category_id)
                    ->orWhere('brand_id', $product->brand_id);
            })
            ->limit($limit)
            ->latest()
            ->get();
    }

    public function findByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();
    }

    public function getRecentlyViewed(array $productIds, int $limit = 6): Collection
    {
        if (empty($productIds)) {
            return new Collection([]);
        }

        return $this->model
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->limit($limit)
            ->get();
    }

    public function syncImages(int $productId, array $images): void
    {
        ProductImage::where('product_id', $productId)->delete();

        foreach ($images as $imageData) {
            ProductImage::create([
                'product_id' => $productId,
                'url' => $imageData['url'],
                'alt' => $imageData['alt'] ?? null,
                'is_primary' => $imageData['is_primary'] ?? false,
                'sort_order' => $imageData['sort_order'] ?? 0,
                'variant_id' => $imageData['variant_id'] ?? null,
            ]);
        }
    }

    public function getImages(int $productId): Collection
    {
        return ProductImage::where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }

    public function deleteImages(int $productId): void
    {
        ProductImage::where('product_id', $productId)->delete();
    }

    // Merchant operations
    public function syncMerchants(int $productId, array $merchants): void
    {
        ProductMerchant::where('product_id', $productId)->delete();

        foreach ($merchants as $merchantData) {
            ProductMerchant::create([
                'product_id' => $productId,
                'name' => $merchantData['name'],
                'url' => $merchantData['url'],
                'price' => $merchantData['price'],
                'is_available' => $merchantData['is_available'] ?? true,
                'variant_id' => $merchantData['variant_id'] ?? null,
                'last_price_check' => now(),
            ]);
        }
    }

    public function getMerchants(int $productId): Collection
    {
        return ProductMerchant::where('product_id', $productId)->get();
    }

    public function deleteMerchants(int $productId): void
    {
        ProductMerchant::where('product_id', $productId)->delete();
    }

    // Variant operations
    public function syncVariants(int $productId, array $variants): void
    {
        ProductVariant::where('product_id', $productId)->delete();

        foreach ($variants as $variantData) {
            ProductVariant::create([
                'product_id' => $productId,
                'sku' => $variantData['sku'],
                'attributes' => $variantData['attributes'] ?? [],
                'price_modifier' => $variantData['price_modifier'] ?? 0,
                'is_active' => $variantData['is_active'] ?? true,
            ]);
        }
    }

    public function getVariants(int $productId): Collection
    {
        return ProductVariant::where('product_id', $productId)->get();
    }

    public function deleteVariants(int $productId): void
    {
        ProductVariant::where('product_id', $productId)->delete();
    }

// Specification operations
    public function syncSpecifications(int $productId, array $specifications): void
    {
        ProductSpecification::where('product_id', $productId)->delete();

        foreach ($specifications as $specData) {
            ProductSpecification::create([
                'product_id' => $productId,
                'category' => $specData['category'],
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
}