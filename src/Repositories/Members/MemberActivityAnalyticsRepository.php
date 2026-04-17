<?php

namespace App\Repositories\Members;

use App\Models\MemberActivityAnalytics;
use App\Repositories\Repository;

class MemberActivityAnalyticsRepository extends Repository
{
    public function upsert(int $memberId, int $siteId, array $data): MemberActivityAnalytics
    {
        return MemberActivityAnalytics::updateOrCreate(
            [
                'member_id' => $memberId,
                'site_id' => $siteId,
            ],
            $data
        );
    }

    protected function getModelClass(): string
    {
        return MemberActivityAnalytics::class;
    }
}