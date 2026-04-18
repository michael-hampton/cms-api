<?php

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\Campaign;
use App\Repositories\Repository;

class CampaignRepository extends Repository
{
    public function paginateForSite(int $siteId, int $perPage = 20, int $page = 1): array
    {
        return Campaign::with('segment')
            ->where('site_id', $siteId)
            ->orderBy('name')
            ->paginate($perPage, $page);
    }

    public function existsBySlugForSite(string $slug, int $siteId, ?int $excludeId = null): bool
    {
        $query = Campaign::query()
            ->where('site_id', $siteId)
            ->where('slug', trim($slug));

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

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
