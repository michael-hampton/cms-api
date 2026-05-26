<?php

namespace App\Services\OpenCollab;

use App\Repositories\OpenCollab\RbacRepository;

class RbacAuditLogger
{
    public function __construct(
        private readonly RbacRepository $rbacRepository,
    ) {
    }

    public function log(string $action, ?int $siteId = null, ?int $actorUserId = null, ?int $targetUserId = null, array $payload = []): void
    {
        $this->rbacRepository->createAuditLog($siteId, $actorUserId, $targetUserId, $action, $payload);
    }
}
