<?php

namespace App\Repositories\Members;

use App\Models\MemberStat;
use App\Repositories\Repository;

class MemberSegmentationProfileRepository extends Repository
{
    /**
     * Returns the latest persisted segmentation profile from member_stats.data.
     *
     * @return array<string, mixed>|null
     */
    public function getLatestProfile(int $memberId, int $siteId): ?array
    {
        $memberStat = MemberStat::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->first();

        if ($memberStat === null || !is_array($memberStat->data)) {
            return null;
        }

        return $memberStat->data;
    }

    protected function getModelClass(): string
    {
        return MemberStat::class;
    }
}
