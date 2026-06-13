<?php

namespace App\Events\OpenCollab;

use App\Models\Page;

class ChangesRequestedEvent
{
    public function __construct(
        public readonly Page $page,
        public readonly int $moderatorId,
        public readonly string $notes,
    ) {
    }
}