<?php

namespace App\Services\MemberInsights\Campaigns;


use App\Repositories\MemberInsights\CampaignDeliveryRepository;
use App\Repositories\MemberInsights\CampaignEventRepository;

/**
 * Tickets 11, 12, 13 — Campaign Analytics Engine.
 *
 * Provides all aggregate metrics:
 *   - Ticket 11: per-campaign delivery + engagement summary
 *   - Ticket 12: per-audience breakdown ("inactive users open 3× more")
 *   - Ticket 13: per-block performance ("which blocks drive clicks")
 *
 * All methods are read-only.  No writes here — recording happens in
 * CampaignDeliveryRepository and CampaignEventRepository.
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
    // Ticket 11 — Campaign-level summary
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

    // =========================================================================
    // Ticket 12 — Audience-level breakdown
    // =========================================================================

    /**
     * Return delivery + engagement metrics grouped by audience_key.
     *
     * Example output:
     * [
     *   'inactive_users'     => ['deliveries' => 500, 'open_rate' => 34.2, ...],
     *   'highly_active_users' => ['deliveries' => 200, 'open_rate' => 11.5, ...],
     * ]
     *
     * Note: open/click counts per audience require a join that is deferred to the
     * repository layer; here we show deliveries. Full open/click per-audience
     * attribution requires storing audience_key on CampaignEvent as well, which
     * is a Phase 2 enhancement (see PHASE notes in tickets).
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
    // Ticket 13 — Block-level performance
    // =========================================================================

    /**
     * Sort block_keys by clicks descending and return ranked list.
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
     * Return click counts per block_key for a campaign.
     * Blocks with zero clicks are not included (they won't be in the DB).
     *
     * @return array<string, int>  e.g. ['welcome_block' => 120, 'churn_block' => 45]
     */
    public function blockPerformance(int $campaignId): array
    {
        return $this->eventRepository->clicksByBlockKey($campaignId);
    }
}