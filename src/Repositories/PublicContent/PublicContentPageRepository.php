<?php

namespace App\Repositories\PublicContent;

use App\Models\Page;
use App\Repositories\Repository;

final class PublicContentPageRepository extends Repository
{
    public function findPublishedById(int $pageId, int $siteId, array $relations = []): ?Page
    {
        $query = $relations === [] ? Page::query() : Page::with($relations);

        $page = $query
            ->where('id', $pageId)
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->first();

        return $page instanceof Page ? $page : null;
    }

    protected function getModelClass(): string
    {
        return Page::class;
    }
}
