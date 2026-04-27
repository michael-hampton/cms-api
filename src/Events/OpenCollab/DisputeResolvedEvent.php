<?php

namespace App\Events\OpenCollab;

class DisputeResolvedEvent
{
    public function __construct(
        public int    $userId,
        public int    $disputeId,
        public string $outcome
    )
    {
    }
}