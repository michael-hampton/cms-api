<?php

namespace App\Events\OpenCollab;

use App\Enums\OpenCollab\RejectionReason;
use App\Models\Page;

/**
 * Fired when an admin rejects a contributor article.
 * Listeners: notify contributor, write activity event.
 */
class ArticleRejectedEvent
{
    public function __construct(
        public readonly Page            $page,
        public readonly int             $adminId,
        public readonly RejectionReason $reason,
        public readonly int $userId,
        public readonly ?string         $notes,
    )
    {
    }
}