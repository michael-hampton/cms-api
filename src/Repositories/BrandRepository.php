<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Brand;
use App\Models\Product;
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

    protected function getModelClass(): string
    {
        return Brand::class;
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
}