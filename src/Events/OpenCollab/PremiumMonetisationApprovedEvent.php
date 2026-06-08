<?php

namespace App\Events\OpenCollab;

use App\Models\Page;

final class PremiumMonetisationApprovedEvent
{
    public function __construct(
        public readonly Page $page,
        public readonly int  $adminId,
    ) {}
}