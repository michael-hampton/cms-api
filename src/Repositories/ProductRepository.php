<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Product;
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

    protected function getModelClass(): string
    {
       return Product::class;
    }
}