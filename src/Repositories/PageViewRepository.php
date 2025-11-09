<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Page;
use App\Models\PageView;

class PageViewRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PageView::class;
    }

    public function recordView(
        int     $pageId,
        ?int    $memberId,
        int     $siteId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referer = null
    ): PageView
    {
        return PageView::recordView(
            $pageId,
            $memberId,
            $siteId,
            $ipAddress,
            $userAgent,
            $referer
        );
    }

    public function getPageViews(int $pageId, ?int $limit = null): Collection
    {
        $query = $this->where('page_id', $pageId)
            ->orderBy('viewed_at', 'desc');

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

    public function getRecentlyViewedPages(int $memberId, int $limit = 10): Collection
    {
        // Get all views for this member, ordered by most recent
        $allViews = $this->where('member_id', $memberId)
            ->orderBy('viewed_at', 'desc')
            ->get();

        if ($allViews->isEmpty()) {
            return new Collection([]);
        }

        // Manually deduplicate by page_id, keeping only the most recent view
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

        // Eager load page relationships
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