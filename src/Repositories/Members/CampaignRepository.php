<?php

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\Campaign;
use App\Repositories\Repository;

class CampaignRepository extends Repository
{
    /**
     * @param string[] $segmentKeys
     * @return Collection<Campaign>
     */
    public function matchActiveBySegmentKeys(array $segmentKeys): Collection
    {
        if ($segmentKeys === []) {
            return collect();
        }

        return Campaign::whereHas('segment', fn($query) => $query->whereIn('key', $segmentKeys))
            ->where('is_active', true)
            ->with('segment')
            ->orderByDesc('priority')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Campaign::class;
    }
}
