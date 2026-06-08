<?php

namespace App\Events\OpenCollab;

use App\Enums\OpenCollab\PremiumMonetisationDisabledReason;
use App\Models\Page;

final class PremiumMonetisationDisabledEvent
{
    public function __construct(
        public readonly Page                               $page,
        public readonly int                               $adminId,
        public readonly string $reason,
    ) {}
}