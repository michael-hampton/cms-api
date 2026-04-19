<?php

namespace App\Services\MemberInsights\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignExecution;
use App\Models\Model;

/**
 * Records a campaign send in the audit log.
 *
 * Responsibility is narrow: one write, nothing else.
 * Called by SendCampaignJob after delivery is handed off to Laravel.
 */
class CampaignExecutionLogger
{
    public function log(int $memberId, Campaign $campaign, string $segmentKey): Model
    {
        return CampaignExecution::create([
            'member_id' => $memberId,
            'campaign_id' => $campaign->id,
            'segment_key' => $segmentKey,
            'sent_at' => now_datetime(),
        ]);
    }
}