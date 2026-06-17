<?php

namespace App\DTO\OpenCollab;

final readonly class ContributorRoleAssignmentRequest
{
    public function __construct(
        public int $userId,
        public int $siteId,
        public string $role,
        public ?int $actorUserId = null,
        public ?string $reason = null,
    ) {
    }
}
