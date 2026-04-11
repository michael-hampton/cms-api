<?php

namespace App\Events\OpenCollab;

use App\Models\Page;

/**
 * Fired when an admin approves a contributor article.
 * Listeners: notify contributor, write activity event.
 */
class ArticleApprovedEvent
{
    public function __construct(
        public readonly Page $page,
        public readonly int  $adminId,
    )
    {
    }
}