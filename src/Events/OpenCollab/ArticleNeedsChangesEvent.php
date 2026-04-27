<?php

namespace App\Events\OpenCollab;

class ArticleNeedsChangesEvent
{
    public function __construct(
        public int    $userId,
        public int    $articleId,
        public string $feedback
    )
    {
    }
}