<?php

namespace App\Repositories\Cms\Briefs;

use App\Models\BriefCollaborator;
use App\Repositories\Repository;

class BriefCollaboratorRepository extends Repository
{
    public function getForBrief(int $briefId): array
    {
        return BriefCollaborator::where('brief_id', $briefId)
            ->with(['user'])
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    public function removeForUser(int $briefId, int $userId): bool
    {
        return BriefCollaborator::where('brief_id', $briefId)
                ->where('user_id', $userId)
                ->delete() > 0;
    }

    protected function getModelClass(): string
    {
        return BriefCollaborator::class;
    }

    public function findByBriefAndUser(int $briefId, int $userId): ?BriefCollaborator
    {
        return BriefCollaborator::where('brief_id', $briefId)
            ->where('user_id', $userId)
            ->first();
    }
}