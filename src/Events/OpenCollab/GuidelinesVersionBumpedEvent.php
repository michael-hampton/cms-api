<?php

namespace App\Events\OpenCollab;

use App\Models\Guideline;

/**
 * Fired when a new guidelines version is published for a site.
 *
 * Listeners must:
 *   1. Invalidate (syncStatus) all contributors on this site.
 *   2. Notify affected contributors that acknowledgement is required.
 */
class GuidelinesVersionBumpedEvent
{
    public function __construct(
        public readonly Guideline $guideline,
        public readonly int       $siteId,
        public readonly int       $newVersion,
        public readonly int $userId
    )
    {
    }
}