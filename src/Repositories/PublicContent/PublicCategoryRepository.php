<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Category;
use App\Repositories\Repository;

class PublicCategoryRepository extends Repository
{
    public function getActiveWithPages(int $siteId): Collection
    {
        return Category::where('site_id', $siteId)
            ->where('is_active', true)
            ->withCount('pages')
            ->whereHas('pages')
            ->orderBy('name')
            ->get();
    }

    public function getAll(int $siteId): Collection
    {
        return Category::where('site_id', $siteId)
            ->orderBy('name')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Category::class;
    }
}
