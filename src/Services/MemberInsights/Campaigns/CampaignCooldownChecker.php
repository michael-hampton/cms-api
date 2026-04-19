<?php

namespace App\Services\MemberInsights\Campaigns;

use App\Models\Campaign;
use App\Repositories\MemberInsights\CampaignExecutionRepository;

/**
 * Determines whether a campaign is eligible to be sent to a member,
 * based on the campaign's cooldown_hours and prior execution history.
 *
 * No writes — purely a read-only guard.
 */
class CampaignCooldownChecker
{
    public function __construct(
        private readonly CampaignExecutionRepository $campaignExecutionRepository,
    )
    {
    }

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

        return !$this->campaignExecutionRepository->hasRecentExecution($memberId, $campaign->id, $threshold);
    }
}
