<?php

namespace App\Events\OpenCollab;

use App\Models\Page;

/**
 * Fired when a contributor publishes a page.
 *
 * Listener: App\Listeners\OpenCollab\NotifyAdminOfContributorPublication
 * — Sends an admin notification so editors can review newly published contributor content.
 */
class PagePublishedByContributorEvent
{
    public function __construct(
        public readonly Page $page,
        public readonly int  $contributorId,
    )
    {
    }
}