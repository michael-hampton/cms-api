<?php

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\PageLike;
use App\Repositories\Repository;

class PageLikeRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PageLike::class;
    }

    public function toggleLike(int $pageId, int $memberId, int $siteId): array
    {
        return PageLike::toggleAction($pageId, $memberId, $siteId, 'like');
    }

    public function isLikedBy(int $pageId, int $memberId, int $siteId): bool
    {
        return PageLike::hasAction($pageId, $memberId, $siteId, 'like');
    }

    public function getLikeCount(int $pageId): int
    {
        return PageLike::getLikeCount($pageId);
    }

    public function getMemberLikeCount(int $memberId, int $siteId): int
    {
        return PageLike::getMemberLikeCount($memberId, $siteId);
    }

    public function getMemberLikedPages(int $memberId, int $siteId, ?int $limit = null): Collection
    {
        return PageLike::getMemberLikedPages($memberId, $siteId, $limit);
    }

    public function getPageLikes(int $pageId, ?int $limit = null): Collection
    {
        $query = $this->where('page_id', $pageId)
            ->with(['member'])
            ->orderBy('liked_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $this->applySiteFilter($query->get());
    }

    public function toggleSave(int $pageId, int $memberId, int $siteId): array
    {
        return PageLike::toggleAction($pageId, $memberId, $siteId, 'save');
    }

    public function isSavedBy(int $pageId, int $memberId, int $siteId): bool
    {
        return PageLike::hasAction($pageId, $memberId, $siteId, 'save');
    }

    public function getMemberSavedPages(int $memberId, int $siteId, ?int $limit = null): Collection
    {
        return PageLike::getMemberActionPages($memberId, $siteId, 'save', $limit);
    }
}