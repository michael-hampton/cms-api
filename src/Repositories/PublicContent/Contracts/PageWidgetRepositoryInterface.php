<?php

namespace App\Repositories\PublicContent\Contracts;

use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;

interface PageWidgetRepositoryInterface
{
    /**
     * @return list<WidgetLayoutOverride>
     */
    public function getForPage(int $siteId, int $pageId): array;

    public function deleteForPage(int $siteId, int $pageId): void;

    public function upsert(int $siteId, int $pageId, WidgetLayoutOverride $override): void;
}
