<?php

namespace App\Services\PublicContent\Composition;

use App\Framework\Support\Collection;
use App\Models\Page;
use App\Models\PageView;

final class PublicPopularArticleProvider
{
    public function forSite(int $siteId, int $limit = 6): Collection
    {
        $ranked = PageView::query()
            ->selectRaw('page_id, COUNT(*) AS view_count')
            ->where('site_id', $siteId)
            ->groupBy('page_id')
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get();

        if ($ranked->isEmpty()) {
            return new Collection([]);
        }

        $pages = Page::whereIn('id', $ranked->pluck('page_id')->toArray())
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->get()
            ->keyBy('id');

        $result = new Collection();

        foreach ($ranked as $row) {
            if ($pages->has($row->page_id)) {
                $result->push([
                    'page' => $pages->get($row->page_id),
                    'view_count' => (int) $row->view_count,
                ]);
            }
        }

        return $result;
    }
}
