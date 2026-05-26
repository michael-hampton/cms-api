<?php

namespace App\Services\OpenCollab;

use App\Models\OpenCollabRbacAuditLog;

class RbacAuditLogger
{
    public function log(string $action, ?int $siteId = null, ?int $actorUserId = null, ?int $targetUserId = null, array $payload = []): void
    {
        OpenCollabRbacAuditLog::create([
            'site_id' => $siteId,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'payload' => $payload ? json_encode($payload) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
