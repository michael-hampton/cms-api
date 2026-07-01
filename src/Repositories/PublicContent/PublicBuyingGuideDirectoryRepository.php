<?php

namespace App\Repositories\PublicContent;

use App\Framework\Database\QueryBuilder;
use App\Models\Page;
use App\Repositories\Repository;

final class PublicBuyingGuideDirectoryRepository extends Repository
{
    private const string PAGE_TYPE = 'buying-guide';

    public function basePagesQuery(int $siteId): QueryBuilder
    {
        return Page::where('pages.site_id', $siteId)
            ->where('pages.status', 'published')
            ->where('pages.page_type', self::PAGE_TYPE)
            ->with(['metadata', 'categories', 'tags', 'authors']);
    }

    protected function getModelClass(): string
    {
        return Page::class;
    }
}