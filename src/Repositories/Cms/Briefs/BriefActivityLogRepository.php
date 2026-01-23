<?php

namespace App\Repositories\Cms\Briefs;

use App\Models\BriefActivityLog;
use App\Repositories\Repository;

class BriefActivityLogRepository extends Repository
{
    public function getForBrief(int $briefId, int $limit = 100): array
    {
        return BriefActivityLog::where('brief_id', $briefId)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    protected function getModelClass(): string
    {
        return BriefActivityLog::class;
    }
}