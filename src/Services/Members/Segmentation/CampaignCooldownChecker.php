<?php

namespace App\Services\Members\Segmentation;

use App\Models\Campaign;
use App\Models\CampaignExecution;

/**
 * Determines whether a campaign is eligible to be sent to a member,
 * based on the campaign's cooldown_hours and prior execution history.
 *
 * No writes — purely a read-only guard.
 */
final class CampaignCooldownChecker
{
    /**
     * Returns true when the campaign MAY be sent (not within cooldown).
     * Returns false when a recent execution blocks delivery.
     */
    public function isEligible(int $memberId, Campaign $campaign): bool
    {
        if ($campaign->cooldown_hours <= 0) {
            return true;
        }

        $threshold = now_datetime()->subHours($campaign->cooldown_hours);

        return !CampaignExecution::where('member_id', $memberId)
            ->where('campaign_id', $campaign->id)
            ->where('sent_at', '>=', $threshold)
            ->exists();
    }
}