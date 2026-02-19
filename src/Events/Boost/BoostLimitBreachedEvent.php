<?php

namespace App\Events\Boost;

use App\Models\Boost;

class BoostLimitBreachedEvent
{
    public function __construct(
        public readonly Boost     $boost,
        public readonly string    $limitType,
        public readonly float|int $limitValue,
        public readonly float|int $currentValue,
    )
    {
    }
}