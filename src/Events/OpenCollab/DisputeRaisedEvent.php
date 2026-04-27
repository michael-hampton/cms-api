<?php

namespace App\Events\OpenCollab;

class DisputeRaisedEvent
{
    public function __construct(
        public int $userId,
        public int $disputeId
    )
    {
    }
}