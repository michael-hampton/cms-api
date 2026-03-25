<?php

namespace App\Repositories\Cms;

use App\Framework\Support\Collection;
use App\Models\Brand;
use App\Models\Product;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class BrandRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('brand');
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Brand::active()->withCount(['products']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function getAllWithProductCounts(?int $siteId)
    {
        return Brand::query()
            ->where('site_id', $siteId)
            ->withCount('products') // assumes Brand has products() relationship
            ->orderByDesc('products_count')
            ->get()
            ->map(fn($brand) => (object)[
                'id' => $brand->id,
                'name' => $brand->name,
                'product_count' => $brand->products_count,
            ]);
    }

    public function findBySlug(string $slug): ?Brand
    {
        return Brand::where('slug', $slug)->first();
    }

    public function getActiveBrands(): Collection
    {
        return Brand::active()->orderBy('name', 'asc')->get();
    }

    public function getAlternatives(int $brandId): Collection
    {
        return Brand::active()
            ->where('id', '!=', $brandId)
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getProductsByBrandId(int $brandId): Collection
    {
        return Product::where('brand_id', $brandId)->get();
    }

    protected function getModelClass(): string
    {
        return Brand::class;
    }
}