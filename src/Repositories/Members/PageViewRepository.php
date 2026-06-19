<?php

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\Page;
use App\Models\PageView;
use App\Repositories\Repository;

class PageViewRepository extends Repository
{
    public function countForMember(int $memberId, int $siteId): int
    {
        return $this->countWhere([
            'member_id' => $memberId,
            'site_id' => $siteId,
        ]);
    }

    protected function getModelClass(): string
    {
        return PageView::class;
    }

    public function recordView(
        int $pageId,
        ?int $memberId,
        int $siteId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referer = null
    ): PageView {
        return PageView::recordView($pageId, $memberId, $siteId, $ipAddress, $userAgent, $referer);
    }

    public function getPageViews(int $pageId, ?int $limit = null): Collection
    {
        $query = $this->where('page_id', $pageId)->orderBy('viewed_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getMemberPageViews(int $memberId, ?int $limit = null): Collection
    {
        $query = $this->where('member_id', $memberId)
            ->with(['page'])
            ->orderBy('viewed_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getUniquePagesViewedByMember(int $memberId, int $siteId): int
    {
        return PageView::getMemberViewCount($memberId, $siteId);
    }

    public function getTotalViewsForPage(int $pageId): int
    {
        return PageView::getTotalViewCount($pageId);
    }

    public function getUniqueViewsForPage(int $pageId): int
    {
        return PageView::getUniqueViewCount($pageId);
    }

    public function getMostPopularArticles(int $siteId, int $limit = 6): Collection
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

        $articles = new Collection();

        foreach ($ranked as $row) {
            if ($pages->has($row->page_id)) {
                $articles->push([
                    'page' => $pages->get($row->page_id),
                    'view_count' => (int) $row->view_count,
                ]);
            }
        }

        return $articles;
    }

    public function getRecentlyViewedPages(int $memberId, int $limit = 10): Collection
    {
        $allViews = $this->where('member_id', $memberId)
            ->orderBy('viewed_at', 'desc')
            ->get();

        if ($allViews->isEmpty()) {
            return new Collection([]);
        }

        $uniqueViews = new Collection();
        $seenPageIds = [];

        foreach ($allViews as $view) {
            if (!in_array($view->page_id, $seenPageIds)) {
                $uniqueViews->push($view);
                $seenPageIds[] = $view->page_id;

                if ($uniqueViews->count() >= $limit) {
                    break;
                }
            }
        }

        if (!$uniqueViews->isEmpty()) {
            $pageIds = $uniqueViews->pluck('page_id')->toArray();
            $pages = Page::whereIn('id', $pageIds)->get()->keyBy('id');

            foreach ($uniqueViews as $view) {
                if ($pages->has($view->page_id)) {
                    $view->setRelation('page', $pages->get($view->page_id));
                }
            }
        }

        return $uniqueViews;
    }
}
