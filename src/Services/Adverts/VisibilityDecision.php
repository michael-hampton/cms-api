<?php

namespace App\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;

class VisibilityDecision
{
    public function __construct(
        public readonly bool               $shouldRender,
        public readonly ?SuppressionReason $reason = null,
        public readonly array              $metadata = []
    )
    {
    }

    public static function show(array $metadata = []): self
    {
        return new self(true, null, $metadata);
    }

    public static function hide(SuppressionReason $reason): self
    {
        return new self(false, $reason);
    }
}