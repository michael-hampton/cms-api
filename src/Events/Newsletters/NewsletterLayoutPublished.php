<?php

namespace App\Events\Newsletters;

use App\Models\NewsletterLayoutVersion;

/**
 * Fired when a layout version transitions to Published state.
 * Listeners: log audit trail, notify dependent newsletter editors, warm caches.
 */
class NewsletterLayoutPublished
{
    public function __construct(
        public readonly NewsletterLayoutVersion $version,
    )
    {
    }
}