<?php

namespace App\Repositories\Cms\Briefs;

use App\Models\BriefTask;
use App\Repositories\Repository;

class BriefTaskRepository extends Repository
{
    public function getForBrief(int $briefId, bool $includeCompleted = true): array
    {
        $query = BriefTask::where('brief_id', $briefId)
            ->with(['assignee', 'creator']);

        if (!$includeCompleted) {
            $query->where('status', '!=', 'completed');
        }

        return $query->orderBy('status')
            ->orderBy('due_date')
            ->get()
            ->toArray();
    }

    public function getPending(int $userId): array
    {
        return BriefTask::where('assigned_to', $userId)
            ->where('status', '!=', 'completed')
            ->with(['brief', 'creator'])
            ->orderBy('due_date')
            ->get()
            ->toArray();
    }

    protected function getModelClass(): string
    {
        return BriefTask::class;
    }
}