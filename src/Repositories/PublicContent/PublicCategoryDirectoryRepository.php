<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Category;
use App\Models\Page;
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
        return Page::with(['metadata', 'categories', 'tags', 'authors'])
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->whereHas('categories', static fn($query) => $query->where('categories.id', $categoryId))
            ->orderByDesc('published_at')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Category::class;
    }
}
