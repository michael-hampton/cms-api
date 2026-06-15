<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Page;
use App\Repositories\Repository;

final class PublicActivityFeedRepository extends Repository
{
    public function latestPublished(int $siteId, int $limit = 10): Collection
    {
        return Page::with(['categories', 'tags', 'authors', 'products', 'comments', 'metadata'])
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    protected function getModelClass(): string
    {
        return Page::class;
    }
}
