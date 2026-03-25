<?php

namespace App\Repositories\Cms;

use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Category;
use App\Models\Model;
use App\Models\PageCategory;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class CategoryRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('category');
        $this->searchEngine = new SearchEngine($config);
    }

    public function getAllWithProductCounts(?int $siteId)
    {
        return $this->query()
            ->where('site_id', $siteId)
            ->withCount('products') // assumes Category has products() relationship
            ->orderByDesc('products_count')
            ->get()
            ->map(fn($category) => (object)[
                'id' => $category->id,
                'name' => $category->name,
                'product_count' => $category->products_count,
            ]);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Category::query()->withCount(['pages', 'products']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function getRootCategories(): Collection
    {
        return Category::roots()
            ->active()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getChildCategories(int $parentId): Collection
    {
        return $this->where('parent_id', $parentId)
            ->active()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getCategoryTree(): array
    {
        $allCategories = $this->getActive();
        return $this->buildTree($allCategories);
    }

    public function getActive(): Collection
    {
        $query = Category::active();
        return $this->applySiteFilter($query)->get();
    }

    private function buildTree(Collection $categories, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category->parent_id == $parentId) {
                $categoryArray = $category->toArray();
                $categoryArray['children'] = $this->buildTree($categories, $category->id);
                $tree[] = $categoryArray;
            }
        }

        return $tree;
    }

    public function findOrCreateByName(string $name, int $siteId): Model
    {
        $slug = Str::slug($name, [$this, 'findBySlug'], '-', 'en', true);

        $existing = $this->findBySlug($slug, $siteId);

        if ($existing) {
            return $existing;
        }

        return $this->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'site_id' => $siteId
        ]);
    }

    public function findBySlug(string $slug, ?int $siteId = null): ?Category
    {
        $query = Category::bySlug($slug);
        return $siteId ? $query->where('site_id', $siteId)->first() : $this->applySiteFilter($query)->first();
    }

    public function getPopularCategories(int $limit = 10): Collection
    {
        return Category::withCount('pages')
            ->active()
            ->orderBy('pages_count', 'desc')
            ->orderBy('name', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getAlternatives(int $excludeId): Collection
    {
        return Category::where('id', '!=', $excludeId)->get();
    }

    public function getPagesByCategoryId(int $categoryId, ?int $limit = null): Collection
    {
        $query = PageCategory::where('category_id', $categoryId)
            ->orderBy('created_at', 'desc');

        if (!empty($limit)) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getBySiteId(int $siteId): array
    {
        $categories = Category::where('site_id', $siteId)
            ->withCount('pages', function ($query) use ($siteId) {
                $query->where('status', 'published')->where('site_id', $siteId);
            })
            ->orderBy('name', 'asc')
            ->get();


        return $categories->filter(function ($category) {
            return $category->pages_count > 0;
        })->toArray();

    }

    protected function getModelClass(): string
    {
        return Category::class;
    }
}