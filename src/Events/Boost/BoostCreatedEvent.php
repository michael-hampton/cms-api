<?php

namespace App\Events\Boost;

use App\Models\Boost;

class BoostCreatedEvent
{
    public function __construct(public readonly Boost $boost)
    {
    }
}