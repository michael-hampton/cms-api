<?php

namespace App\Repositories\MemberInsights;

use App\Enums\Member\CampaignPurpose;
use App\Models\CampaignExecution;
use App\Repositories\Repository;

class CampaignExecutionRepository extends Repository
{
    public function hasRecentExecution(int $memberId, int $campaignId, \DateTimeInterface $threshold): bool
    {
        return CampaignExecution::where('member_id', $memberId)
            ->where('campaign_id', $campaignId)
            ->where('sent_at', '>=', $threshold->format('Y-m-d H:i:s'))
            ->exists();
    }

    public function countMarketingExecutionsSince(int $memberId, \DateTimeInterface $since): int
    {
        return $this->model
            ->newQuery()
            ->where('member_id', $memberId)
            ->where('sent_at', '>=', $since->format('Y-m-d H:i:s'))
            ->count();
    }
    protected function getModelClass(): string
    {
        return CampaignExecution::class;
    }
}
