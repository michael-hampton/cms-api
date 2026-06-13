<?php

namespace App\Events\OpenCollab;

use App\Models\ContentRiskMarker;

class RiskMarkerStatusChangedEvent
{
    public function __construct(
        public readonly ContentRiskMarker $marker,
        public readonly int $actorUserId,
    ) {
    }
}