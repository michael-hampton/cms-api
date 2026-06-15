<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Comment;
use App\Repositories\Repository;

final class PublicCommentRepository extends Repository
{
    public function getApprovedForPage(int $pageId, int $siteId): Collection
    {
        return Comment::with(['member'])
            ->where('page_id', $pageId)
            ->where('site_id', $siteId)
            ->where('status', 'approved')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Comment::class;
    }
}
