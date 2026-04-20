<?php

namespace App\Services\MemberInsights\Campaigns;

use App\Framework\Support\Collection;
use App\Models\Campaign;

/**
 * Ticket 10 — Campaign Priority Resolution.
 *
 * When a member qualifies for multiple campaigns in a single segmentation
 * pass, only the highest-priority campaign should be dispatched (or a small
 * capped number of them, limited by ProcessMemberSegmentationJob::MAX_CAMPAIGNS_PER_RUN).
 *
 * Rules:
 *   1. Campaigns are sorted by `priority` column descending (higher = first).
 *   2. Within the same priority tier, the most recently created campaign wins
 *      (consistent, deterministic tie-breaking).
 *   3. The caller decides how many top campaigns to actually send; this class
 *      only sorts and returns the ranked list.
 *
 * This is intentionally a pure value-object utility — no DB access, no state.
 */
final class CampaignPriorityResolver
{
    /**
     * Return only the top $limit campaigns by priority.
     *
     * @param Campaign[] $campaigns
     * @param int $limit
     * @return Campaign[]
     */
    public function top(array $campaigns, int $limit): array
    {
        return array_slice($this->rank($campaigns), 0, $limit);
    }

    /**
     * Return campaigns sorted highest-priority first.
     *
     * @param Campaign[] $campaigns
     * @return Campaign[]
     */
    public function rank(Collection $campaigns): Collection
    {
        return $campaigns
            ->sortByDesc(fn(Campaign $c) => $c->priority ?? 0)
            ->sortByDesc(fn(Campaign $c) => $c->id ?? 0)
            ->values();
    }
}