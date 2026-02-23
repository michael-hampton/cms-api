<?php

namespace App\Events\Newsletters;

use App\Models\NewsletterSnapshot;

/**
 * Fired when a newsletter snapshot is created after publish.
 * Listeners: generate view-in-browser token, warm CDN cache, trigger audit.
 */
class NewsletterSnapshotCreated
{
    public function __construct(
        public readonly NewsletterSnapshot $snapshot,
    )
    {
    }
}