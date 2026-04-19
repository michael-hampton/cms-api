<?php

namespace App\Repositories\MemberInsights;

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

    protected function getModelClass(): string
    {
        return CampaignExecution::class;
    }
}
