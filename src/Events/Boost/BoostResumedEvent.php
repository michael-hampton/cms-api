<?php

namespace App\Events\Boost;

use App\Models\Boost;

class BoostResumedEvent
{
    public function __construct(public readonly Boost $boost)
    {
    }
}