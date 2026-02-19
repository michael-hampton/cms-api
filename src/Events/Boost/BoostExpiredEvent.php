<?php

namespace App\Events\Boost;

use App\Models\Boost;

class BoostExpiredEvent
{
    public function __construct(public readonly Boost $boost)
    {
    }
}