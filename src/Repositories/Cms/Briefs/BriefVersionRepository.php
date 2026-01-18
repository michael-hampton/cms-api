<?php

namespace App\Repositories\Cms\Briefs;

use App\Models\BriefVersion;
use App\Repositories\Repository;

class BriefVersionRepository extends Repository
{
    public function getForBrief(int $briefId, int $limit = 50): array
    {
        return BriefVersion::where('brief_id', $briefId)
            ->with(['creator'])
            ->orderBy('version_number', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getLatest(int $briefId): ?BriefVersion
    {
        return BriefVersion::where('brief_id', $briefId)
            ->orderBy('version_number', 'desc')
            ->first();
    }

    protected function getModelClass(): string
    {
        return BriefVersion::class;
    }
}