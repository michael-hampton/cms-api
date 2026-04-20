<?php

namespace App\Services\MemberInsights;


use App\Repositories\MemberInsights\CampaignDeliveryRepository;
use App\Repositories\MemberInsights\CampaignEventRepository;

/**
 * Tickets 11, 12, 13 — Campaign Analytics Engine.
 *
 * Fixed from original:
 *   - getDailyEngagement() added (used by summary endpoint for trend data)
 *   - summarise() now returns the same shape the controller documents
 *   - byAudience() — no change, was correct
 *   - rankedBlocks() / blockPerformance() — no change, were correct
 */
final class CampaignAnalyticsService
{
    public function __construct(
        private readonly CampaignDeliveryRepository $deliveryRepository,
        private readonly CampaignEventRepository    $eventRepository,
    )
    {
    }

    // =========================================================================
    // T11 — Campaign-level summary
    // =========================================================================

    /**
     * Overall delivery + engagement summary for a single campaign.
     *
     * @return array{
     *   deliveries: int,
     *   opens: int,
     *   clicks: int,
     *   unique_opens: int,
     *   unique_clicks: int,
     *   open_rate: float,
     *   click_rate: float,
     * }
     */
    public function summarise(int $campaignId): array
    {
        $deliveries = $this->deliveryRepository->getForCampaign($campaignId)->count();
        $opens = $this->eventRepository->countByTypeForCampaign($campaignId, 'open');
        $clicks = $this->eventRepository->countByTypeForCampaign($campaignId, 'click');
        $uniqueOpens = $this->eventRepository->uniqueMemberCountByType($campaignId, 'open');
        $uniqueClicks = $this->eventRepository->uniqueMemberCountByType($campaignId, 'click');

        return [
            'deliveries' => $deliveries,
            'opens' => $opens,
            'clicks' => $clicks,
            'unique_opens' => $uniqueOpens,
            'unique_clicks' => $uniqueClicks,
            'open_rate' => $deliveries > 0 ? round($uniqueOpens / $deliveries * 100, 2) : 0.0,
            'click_rate' => $deliveries > 0 ? round($uniqueClicks / $deliveries * 100, 2) : 0.0,
        ];
    }

    /**
     * 30-day daily series of opens and clicks.
     * Returned as an array the dashboard overview chart can consume directly.
     *
     * @return array<array{date: string, opens: int, clicks: int}>
     */
    public function getDailyEngagement(int $campaignId, int $days = 30): array
    {
        return $this->deliveryRepository->getDailyEngagementSeries($campaignId, $days);
    }

    // =========================================================================
    // T12 — Audience-level breakdown
    // =========================================================================

    /**
     * Return delivery counts grouped by audience_key with share percentage.
     *
     * @return array<string, array{deliveries: int, share_percent: float}>
     */
    public function byAudience(int $campaignId): array
    {
        $deliveriesByAudience = $this->deliveryRepository->countByCampaignAndAudience($campaignId);
        $totalDeliveries = array_sum($deliveriesByAudience);

        $result = [];
        foreach ($deliveriesByAudience as $audienceKey => $count) {
            $result[$audienceKey] = [
                'deliveries' => $count,
                'share_percent' => $totalDeliveries > 0 ? round($count / $totalDeliveries * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    // =========================================================================
    // T13 — Block-level performance
    // =========================================================================

    /**
     * Click counts per block_key, sorted highest first with rank.
     *
     * @return array<int, array{block_key: string, clicks: int, rank: int}>
     */
    public function rankedBlocks(int $campaignId): array
    {
        $raw = $this->blockPerformance($campaignId);
        arsort($raw);

        $ranked = [];
        $rank = 1;
        foreach ($raw as $blockKey => $clicks) {
            $ranked[] = [
                'block_key' => $blockKey,
                'clicks' => $clicks,
                'rank' => $rank++,
            ];
        }

        return $ranked;
    }

    /**
     * Click counts per block_key, unsorted.
     *
     * @return array<string, int>
     */
    public function blockPerformance(int $campaignId): array
    {
        return $this->eventRepository->clicksByBlockKey($campaignId);
    }
}