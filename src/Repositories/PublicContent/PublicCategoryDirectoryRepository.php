<?php

namespace App\Repositories\PublicContent;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageCategory;
use App\Repositories\Repository;

final class PublicCategoryDirectoryRepository extends Repository
{
    public function findActiveBySlug(int $siteId, string $slug): ?Category
    {
        $category = Category::where('site_id', $siteId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        return $category instanceof Category ? $category : null;
    }

    public function getActive(int $siteId): Collection
    {
        return Category::where('site_id', $siteId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getChildren(int $siteId, int $parentId): Collection
    {
        return Category::where('site_id', $siteId)
            ->where('parent_id', $parentId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getPublishedPages(int $siteId, int $categoryId): Collection
    {
        return PageCategory::with(['page.metadata', 'page.categories', 'page.tags', 'page.authors'])
            ->where('category_id', $categoryId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(static fn($item) => $item->page)
            ->filter(static fn($page): bool =>
                $page !== null
                && (int)$page->site_id === $siteId
                && (string)$page->status === 'published'
            );
    }

    public function baseIndexQuery(int $siteId): QueryBuilder
    {
        return Category::where('site_id', $siteId)
            ->where('is_active', true);
    }

    public function basePagesQuery(int $siteId, int $categoryId): QueryBuilder
    {
        return Page::where('pages.site_id', $siteId)
            ->where('pages.status', 'published')
            ->whereHas('categories', static fn(QueryBuilder $q) => $q->where('categories.id', $categoryId))
            ->with(['metadata', 'categories', 'tags', 'authors']);
    }

    protected function getModelClass(): string
    {
        return Category::class;
    }
}
