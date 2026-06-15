<?php

namespace App\DTO\PublicContent;

use App\Models\Territory;

final readonly class PublicContentRegionContext
{
    public function __construct(
        public Territory $territory,
        public ?string $locale = null,
    ) {
    }
}
