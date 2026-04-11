<?php

namespace App\Events\OpenCollab;

use App\Models\Page;

/**
 * Fired when a contributor transitions a page to waiting_approval.
 * Listeners: notify admins, write activity event.
 */
class ArticleSubmittedForReviewEvent
{
    public function __construct(
        public readonly Page $page,
        public readonly int  $contributorId,
    )
    {
    }
}