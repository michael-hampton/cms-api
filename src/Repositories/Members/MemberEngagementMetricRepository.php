<?php

namespace App\Repositories\Members;

use App\Enums\MemberMetricType;
use App\Repositories\Repository;

class MemberEngagementMetricRepository extends Repository
{
    public function record(int $memberId, int $siteId, MemberMetricType $type, ?int $entityId): void
    {
        MemberEngagementMetric::create([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'metric_type' => $type->value,
            'entity_id' => $entityId,
        ]);
    }

    public function countByType(int $memberId, int $siteId, MemberMetricType $type): int
    {
        return MemberEngagementMetric::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('metric_type', $type->value)
            ->count();
    }

    protected function getModelClass(): string
    {
        return MemberEngagementMetric::class;
    }
}