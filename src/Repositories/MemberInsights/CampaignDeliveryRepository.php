<?php

namespace App\Repositories\MemberInsights;

use App\Models\CampaignDelivery;
use App\Models\Model;
use App\Repositories\Repository;

/**
 * Ticket 11 — Campaign Analytics: delivery persistence.
 *
 * Records one row per delivered campaign message.
 * Used by:
 *   - CampaignAnalyticsService (aggregations)
 *   - DeliveryRateLimiter (count today's sends)
 *   - Ticket 12 audience-level breakdowns (audience_key column)
 */
class CampaignDeliveryRepository extends Repository
{
    public function record(
        int    $memberId,
        int    $campaignId,
        string $channel,
        string $audienceKey,
        ?int   $variantId = null,
    ): Model
    {
        return CampaignDelivery::create([
            'member_id' => $memberId,
            'campaign_id' => $campaignId,
            'channel' => $channel,
            'audience_key' => $audienceKey,
            'variant_id' => $variantId,
            'delivered_at' => now_datetime()->format('Y-m-d H:i:s'),
            'token' => bin2hex(random_bytes(16))
        ]);
    }

    public function countMarketingExecutionsSince(int $memberId, \DateTimeInterface $since): int
    {
        return CampaignDelivery::where('member_id', $memberId)
            ->where('delivered_at', '>=', $since->format('Y-m-d H:i:s'))
            ->count();
    }

    public function getForCampaign(int $campaignId): \App\Framework\Support\Collection
    {
        return CampaignDelivery::where('campaign_id', $campaignId)
            ->orderBy('delivered_at', 'desc')
            ->get();
    }

    public function countByCampaignAndAudience(int $campaignId): array
    {
        return CampaignDelivery::where('campaign_id', $campaignId)
            ->selectRaw('audience_key, COUNT(*) as total')
            ->groupBy('audience_key')
            ->get()
            ->pluck('total', 'audience_key')
            ->toArray();
    }

    protected function getModelClass(): string
    {
        return CampaignDelivery::class;
    }
}