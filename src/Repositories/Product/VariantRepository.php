<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class VariantRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('variant');
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = ProductVariant::with([
            'product',
            'images',
            'merchants.merchant'
        ]);

        return $this->searchEngine->search($query, $criteria);
    }

    public function getByProduct(int $productId): Collection
    {
        return ProductVariant::with(['images', 'merchants'])
            ->where('product_id', $productId)
            ->orderBy('sku')
            ->get();
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
                'sort_order' => !empty($imageData['sort_order']) && is_numeric($imageData['sort_order'])
                    ? $imageData['sort_order']
                    : 0,
            ]);
        }
    }

    public function deleteVariantImages(int $variantId): void
    {
        ProductImage::where('variant_id', $variantId)->delete();
    }

    protected function getModelClass(): string
    {
        return ProductVariant::class;
    }
}