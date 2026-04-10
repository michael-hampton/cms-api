<?php

namespace App\Repositories\OpenCollab;

use App\Models\ArticleComment;
use App\Repositories\Repository;

class ArticleCommentRepository extends Repository
{
    /**
     * All top-level comments for an article, with their replies eager-loaded.
     */
    public function forArticle(int $articleId): \App\Framework\Support\Collection
    {
        return ArticleComment::where('article_id', $articleId)
            ->whereNull('parent_id')
            ->with(['replies'])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Adds a comment, optionally as a reply to another.
     */
    public function addComment(
        int     $articleId,
        int     $userId,
        string  $content,
        ?int    $parentId = null,
        ?string $position = null,
    ): ArticleComment
    {
        return $this->create([
            'article_id' => $articleId,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'position' => $position,
            'content' => $content,
        ]);
    }

    protected function getModelClass(): string
    {
        return ArticleComment::class;
    }
}