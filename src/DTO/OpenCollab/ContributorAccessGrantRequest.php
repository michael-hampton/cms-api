<?php

namespace App\DTO\OpenCollab;

final readonly class ContributorAccessGrantRequest
{
    public function __construct(
        public int $userId,
        public int $siteId,
        public ?int $actorUserId = null,
        public ?int $invitationId = null,
        public ?string $reason = null,
    ) {
    }
}
