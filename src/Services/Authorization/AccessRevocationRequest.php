<?php

namespace App\Services\Authorization;

final readonly class AccessRevocationRequest
{
    public function __construct(
        public int $userId,
        public int $siteId,
        public string $capability,
        public ?int $actorUserId = null,
        public ?string $reason = null,
    ) {
    }
}
