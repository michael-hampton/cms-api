<?php

namespace App\Events\OpenCollab;

use App\Models\Guideline;

final class GuidelineDraftCreatedEvent
{
    public function __construct(
        public readonly Guideline $guideline,
        public readonly int       $siteId,
        public readonly ?int      $clonedFromGuidelineId,
        public readonly ?int      $sourceTemplateId,
    )
    {
    }
}