<?php

namespace App\Events\OpenCollab;

use App\Models\ModerationEscalation;

class ModerationEscalationCreatedEvent
{
    public function __construct(
        public readonly ModerationEscalation $escalation,
    ) {
    }
}