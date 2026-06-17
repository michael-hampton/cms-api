<?php

namespace App\Services\Authorization;

final readonly class AccessGrantRequest
{
    public function __construct(
        public int $userId,
        public int $siteId,
        public string $capability,
        public ?int $actorUserId = null,
        public ?int $invitationId = null,
        public ?string $reason = null,
    ) {
    }
}
