<?php

namespace App\Events\Boost;

use App\Models\Boost;

class BoostActivatedEvent
{
    public function __construct(public readonly Boost $boost)
    {
    }
}