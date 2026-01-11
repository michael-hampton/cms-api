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
        return PageLike::toggle($pageId, $memberId, $siteId);
    }

    public function isLikedBy(int $pageId, int $memberId, int $siteId): bool
    {
        return PageLike::isLikedBy($pageId, $memberId, $siteId);
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
}