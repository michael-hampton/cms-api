<?php

namespace App\Repositories\OpenCollab;

use App\Models\AdminActivityLog;
use App\Models\Model;
use App\Repositories\Repository;

class AdminActivityLogRepository extends Repository
{
    public function log(
        int     $adminId,
        int     $targetUserId,
        string  $action,
        array   $payload = [],
        ?string $reason = null,
    ): Model
    {
        return AdminActivityLog::create([
            'admin_id' => $adminId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'payload' => $payload,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    protected function getModelClass(): string
    {
        return Adminactivitylog::class;
    }
}