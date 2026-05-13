<?php

namespace App\Events\OpenCollab;

use App\Models\Guideline;

final class GuidelinePublishedEvent
{
    public function __construct(
        public readonly Guideline $guideline,
        public readonly int       $siteId,
        public readonly int       $version,
        public readonly int       $publishedByUserId,
    )
    {
    }
}