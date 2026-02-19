<?php

namespace App\Exceptions\Boost;

use App\Models\Boost;

class BoostLimitBreachedException extends \RuntimeException
{
    public function __construct(
        public readonly Boost     $boost,
        public readonly string    $limitType,
        public readonly float|int $limitValue,
        public readonly float|int $currentValue,
    )
    {
        parent::__construct(
            "Boost #{$boost->id} has exceeded its {$limitType} limit " .
            "({$currentValue} / {$limitValue}). The boost has been paused."
        );
    }
}