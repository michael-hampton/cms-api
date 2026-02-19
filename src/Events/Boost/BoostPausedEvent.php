<?php

namespace App\Events\Boost;

use App\Models\Boost;

class BoostPausedEvent
{
    public function __construct(public readonly Boost $boost)
    {
    }
}