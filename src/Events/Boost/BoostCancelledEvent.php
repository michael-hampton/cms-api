<?php

namespace App\Events\Boost;

use App\Models\Boost;

class BoostCancelledEvent
{
    public function __construct(public readonly Boost $boost)
    {
    }
}