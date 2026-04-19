<?php

namespace App\Repositories\MemberInsights;

use App\Models\CampaignEvent;
use App\Repositories\Repository;

/**
 * Ticket 11 — Campaign Analytics: engagement event persistence.
 *
 * Stores one row per open/click event.
 * open  = pixel hit (email only)
 * click = tracked link hit (any channel)
 *
 * Ticket 12 note: audience_key stored at delivery time (CampaignDelivery),
 * not repeated here to avoid duplication. Joins on member_id + campaign_id.
 *
 * Ticket 13 note: block_key is stored here to enable block-level attribution.
 */
class CampaignEventRepository extends Repository
{
    public function recordOpen(int $memberId, int $campaignId, ?int $variantId = null): CampaignEvent
    {
        return $this->recordEvent($memberId, $campaignId, 'open', [], $variantId);
    }

    private function recordEvent(
        int    $memberId,
        int    $campaignId,
        string $type,
        array  $metadata,
        ?int   $variantId,
    ): CampaignEvent
    {
        return CampaignEvent::create([
            'member_id' => $memberId,
            'campaign_id' => $campaignId,
            'event_type' => $type,
            'metadata' => $metadata,
            'variant_id' => $variantId,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function recordClick(
        int     $memberId,
        int     $campaignId,
        string  $url,
        ?string $blockKey = null,
        ?int    $variantId = null,
    ): CampaignEvent
    {
        return $this->recordEvent($memberId, $campaignId, 'click', [
            'url' => $url,
            'block_key' => $blockKey,
        ], $variantId);
    }

    public function countByTypeForCampaign(int $campaignId, string $eventType): int
    {
        return CampaignEvent::where('campaign_id', $campaignId)
            ->where('event_type', $eventType)
            ->count();
    }

    public function uniqueMemberCountByType(int $campaignId, string $eventType): int
    {
        return CampaignEvent::where('campaign_id', $campaignId)
            ->where('event_type', $eventType)
            ->distinct('member_id')
            ->count('member_id');
    }

    /**
     * Returns click counts grouped by block_key for Ticket 13 block-level reporting.
     */
    public function clicksByBlockKey(int $campaignId): array
    {
        return CampaignEvent::where('campaign_id', $campaignId)
            ->where('event_type', 'click')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.block_key')) IS NOT NULL")
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.block_key')) as block_key, COUNT(*) as clicks")
            ->groupByRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.block_key'))")
            ->get()
            ->pluck('clicks', 'block_key')
            ->toArray();
    }

    protected function getModelClass(): string
    {
        return CampaignEvent::class;
    }
}