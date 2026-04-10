<?php

namespace App\Repositories\OpenCollab;

use App\Models\ArticleAccess;
use App\Repositories\Repository;

class ArticleAccessRepository extends Repository
{
    public function hasAccessByUserId(int $pageId, int $userId): bool
    {
        return ArticleAccess::where('page_id', $pageId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function hasAccessByEmail(int $pageId, string $email): bool
    {
        return ArticleAccess::where('page_id', $pageId)
            ->where('email', $email)
            ->exists();
    }

    protected function getModelClass(): string
    {
        return ArticleAccess::class;
    }
}