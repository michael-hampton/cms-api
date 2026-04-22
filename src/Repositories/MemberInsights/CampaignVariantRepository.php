<?php

namespace App\Repositories\MemberInsights;

use App\Models\CampaignVariant;
use App\Repositories\Repository;

/**
 * Ticket 14 — A/B Testing: variant repository.
 *
 * campaign_variants schema:
 *   id, campaign_id, key (A/B/...), weight (int, default 50), blocks (JSON)
 */
class CampaignVariantRepository extends Repository
{
    public function findForCampaign(int $campaignId): \App\Framework\Support\Collection
    {
        return CampaignVariant::where('campaign_id', $campaignId)
            ->orderBy('key')
            ->get();
    }

    public function deleteForCampaign(int $campaignId): void
    {
        CampaignVariant::where('campaign_id', $campaignId)->delete();
    }

    protected function getModelClass(): string
    {
        return CampaignVariant::class;
    }
}


/**
 * Ticket 14 — A/B Testing: deterministic variant assignment.
 *
 * A member is assigned a variant once and consistently — the same member
 * always gets the same variant for a given campaign.  This prevents
 * members seeing different content across retries or re-sends.
 *
 * Algorithm:
 *   hash(member_id + campaign_id) mod total_weight
 *   Walk through variants in order; first whose cumulative weight covers
 *   the hash slot wins.
 *
 * Example with weights A=50, B=50:
 *   slot 0-49  → A
 *   slot 50-99 → B
 */
class CampaignVariantAssigner
{
    public function __construct(
        private readonly CampaignVariantRepository $variantRepository,
    )
    {
    }

    /**
     * Return the variant key (e.g. 'A' or 'B') for a member/campaign pair.
     * Returns null when the campaign has no variants configured.
     */
    public function assignVariant(int $memberId, int $campaignId): ?CampaignVariant
    {
        $variants = $this->variantRepository->findForCampaign($campaignId);

        if ($variants->isEmpty()) {
            return null;
        }

        $totalWeight = $variants->sum('weight');

        if ($totalWeight <= 0) {
            return $variants->first();
        }

        // Deterministic slot: consistent for (member, campaign) pair.
        $slot = abs(crc32("{$memberId}:{$campaignId}")) % $totalWeight;

        $cursor = 0;
        foreach ($variants as $variant) {
            $cursor += (int)($variant->weight ?? 0);
            if ($slot < $cursor) {
                return $variant;
            }
        }

        return $variants->last();
    }
}