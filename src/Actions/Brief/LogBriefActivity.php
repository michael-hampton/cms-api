<?php

namespace App\Actions\Brief;

use App\Models\Brief;
use App\Models\BriefActivityLog;

class LogBriefActivity
{
    public static function execute(int $briefId, int $userId, string $action, string $description, array $metadata = []): void
    {
        BriefActivityLog::create([
            'brief_id' => $briefId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata
        ]);

        // Update last activity
        Brief::where('id', $briefId)->update([
            'last_activity_at' => date('Y-m-d H:i:s'),
            'last_activity_user_id' => $userId
        ]);
    }
}