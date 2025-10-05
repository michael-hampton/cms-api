<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Category;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageCategory;
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
        $config = SearchConfigurationFactory::createCategoryConfiguration();
        $this->searchEngine = new SearchEngine($config);
    }

    protected function getModelClass(): string
    {
        return Category::class;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Category::query();
        return $this->searchEngine->search($query, $criteria);
    }

    public function findBySlug(string $slug): ?Category
    {
        return Category::bySlug($slug)->first();
    }

    public function getActive(): Collection
    {
        return Category::active()->get();
    }

    public function getRootCategories(): array
    {
        return Category::roots()->active()->ordered()->get();
    }


    public function getChildCategories(int $parentId): Collection
    {
        return $this->where('parent_id', $parentId)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getCategoryTree(): array
    {
        $allCategories = $this->getActive();
        return $this->buildTree($allCategories);
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

    public function findOrCreateByName(string $name): Model
    {
        $slug = Str::slug($name, [$this, 'findBySlug']);
        $existing = $this->findBySlug($slug);

        if ($existing) {
            return $existing;
        }

        return $this->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true
        ]);
    }

    public function getPopularCategories(int $limit = 10): array
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

        if(!empty($limit)) {
            $query->limit($limit);
        }

        return $query->get();
    }
}