<?php

namespace App\DTO\OpenCollab;

final readonly class ContributorAccessRevocationRequest
{
    public function __construct(
        public int $userId,
        public int $siteId,
        public ?int $actorUserId = null,
        public ?string $reason = null,
    ) {
    }
}
