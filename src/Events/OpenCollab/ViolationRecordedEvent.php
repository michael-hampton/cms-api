<?php

namespace App\Events\OpenCollab;

use App\Models\ContributorViolation;

/**
 * Fired when a violation is recorded against a contributor.
 * Listeners: check thresholds and auto-suspend if needed, notify contributor.
 */
class ViolationRecordedEvent
{
    public function __construct(
        public readonly ContributorViolation $violation,
        public readonly int $userId
    )
    {
    }
}