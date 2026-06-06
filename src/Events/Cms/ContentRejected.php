<?php

namespace App\Events\Cms;

class ContentRejected
{
    public function __construct(
        public readonly string $contentType,
        public readonly int $contentId,
        public readonly int $siteId,
        public readonly int $actorId,
        public readonly string $title,
        public readonly ?int $ownerId = null,
        public readonly ?string $reason = null,
    ) {
    }
}
