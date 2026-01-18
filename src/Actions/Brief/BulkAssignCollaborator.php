<?php

namespace App\Actions\Brief;

use App\Models\BriefCollaborator;

class BulkAssignCollaborator
{
    public static function execute(array $briefIds, int $userId, string $role): int
    {
        $count = 0;
        foreach ($briefIds as $briefId) {
            // Check if already assigned
            $existing = BriefCollaborator::where('brief_id', $briefId)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                $existing->update(['role' => $role]);
            } else {
                BriefCollaborator::create([
                    'brief_id' => $briefId,
                    'user_id' => $userId,
                    'role' => $role,
                    'assigned_at' => date('Y-m-d H:i:s')
                ]);
            }

            LogBriefActivity::execute($briefId, $userId, 'collaborator_added',
                "Assigned as {$role}");

            $count++;
        }

        return $count;
    }
}