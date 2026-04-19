<?php

namespace App\Services\MemberInsights;

use App\Repositories\MemberInsights\CampaignExecutionRepository;

/**
 * Ticket 10 — Delivery Control: Cooldowns & Rate Limiting.
 *
 * Implements two delivery safety constraints:
 *
 *   1. Per-campaign cooldown  — respects Campaign::cooldown_hours (already
 *      handled by CampaignCooldownChecker; this class does NOT duplicate it).
 *
 *   2. Global daily marketing cap — prevents any member from receiving more
 *      than MAX_MARKETING_PER_DAY marketing messages in a 24-hour window,
 *      regardless of which campaign sends them.
 *
 * This class is called by ProcessMemberSegmentationJob before dispatching
 * SendCampaignJob, and by the newsletter send path for campaign-triggered sends.
 *
 * Design:
 *   - Read-only: no writes.  Recording actual sends is the caller's job.
 *   - Uses CampaignExecutionRepository so we don't scatter raw queries.
 *   - Critical: throws on DB errors rather than silently allowing sends.
 */
final class DeliveryRateLimiter
{
    /** Maximum marketing messages a member may receive in a 24-hour window. */
    private const MAX_MARKETING_PER_DAY = 1;

    public function __construct(
        private readonly CampaignExecutionRepository $executionRepository,
    )
    {
    }

    /**
     * Returns true when the member has NOT exceeded the daily marketing cap.
     * Returns false when they have and the send should be skipped.
     */
    public function isUnderDailyMarketingCap(int $memberId): bool
    {
        $threshold = now_datetime()->modify('-24 hours');
        $count = $this->executionRepository->countMarketingExecutionsSince($memberId, $threshold);

        return $count < self::MAX_MARKETING_PER_DAY;
    }

    /**
     * Returns the max allowed per-day marketing messages (for display / tests).
     */
    public function dailyMarketingCap(): int
    {
        return self::MAX_MARKETING_PER_DAY;
    }
}