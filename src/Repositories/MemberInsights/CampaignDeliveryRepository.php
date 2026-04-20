<?php

namespace App\Repositories\MemberInsights;

use App\Models\CampaignDelivery;
use App\Models\Model;
use App\Repositories\Repository;

/**
 * Ticket 11 — Campaign Analytics: delivery persistence.
 *
 * Changes from original:
 *   - record() now generates and stores a unique tracking token
 *   - countMarketingExecutionsSince() renamed to match DeliveryRateLimiter's call
 *   - getDailyTimeSeries() added for the dashboard overview trend chart
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
            'token' => bin2hex(random_bytes(32)),
            'delivered_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Used by DeliveryRateLimiter::isUnderDailyMarketingCap().
     */
    public function countForMemberSince(int $memberId, \DateTimeInterface $since): int
    {
        return CampaignDelivery::where('member_id', $memberId)
            ->where('delivered_at', '>=', $since->format('Y-m-d H:i:s'))
            ->count();
    }

    /**
     * @deprecated use countForMemberSince
     */
    public function countMarketingExecutionsSince(int $memberId, \DateTimeInterface $since): int
    {
        return $this->countForMemberSince($memberId, $since);
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

    /**
     * Returns daily delivery counts for the last N days.
     * Used by the dashboard trend chart on the overview tab.
     *
     * @return array<array{date: string, count: int}>
     */
    public function getDailyTimeSeries(int $campaignId, int $days = 30): array
    {
        $since = now_datetime()->modify("-{$days} days")->format('Y-m-d');

        $rows = CampaignDelivery::where('campaign_id', $campaignId)
            ->where('delivered_at', '>=', $since)
            ->selectRaw('DATE(delivered_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill in zeroes for days with no deliveries so the chart is continuous.
        $indexed = $rows->pluck('count', 'date')->toArray();
        $result = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now_datetime()->modify("-{$i} days")->format('Y-m-d');
            $result[] = ['date' => $date, 'count' => (int)($indexed[$date] ?? 0)];
        }

        return $result;
    }

    /**
     * Returns daily open and click counts for the last N days.
     * Joins campaign_events for the matching campaign.
     *
     * @return array<array{date: string, opens: int, clicks: int}>
     */
    public function getDailyEngagementSeries(int $campaignId, int $days = 30): array
    {
        $since = now_datetime()->modify("-{$days} days")->format('Y-m-d');

        $events = \App\Models\CampaignEvent::where('campaign_id', $campaignId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, event_type, COUNT(*) as count')
            ->groupBy('date', 'event_type')
            ->orderBy('date')
            ->get();

        $byDate = [];
        foreach ($events as $row) {
            $byDate[$row->date][$row->event_type] = (int)$row->count;
        }

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now_datetime()->modify("-{$i} days")->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'opens' => $byDate[$date]['open'] ?? 0,
                'clicks' => $byDate[$date]['click'] ?? 0,
            ];
        }

        return $result;
    }

    protected function getModelClass(): string
    {
        return CampaignDelivery::class;
    }
}