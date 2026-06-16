<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\PageWidget;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Repositories\Repository;

final class PageWidgetRepository extends Repository implements PageWidgetRepositoryInterface
{
    public function getForPage(int $pageId): Collection
    {
        return PageWidget::where('page_id', $pageId)
            ->orderBy('region', 'asc')
            ->orderBy('priority', 'asc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return PageWidget::class;
    }
}
