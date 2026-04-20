<?php

namespace App\Controllers\MemberInsights;

use App\Controllers\Controller;
use App\Enums\Member\CampaignChannel;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\MemberInsights\CampaignDeliveryRepository;
use App\Services\MemberInsights\Audiences\AudienceRegistry;
use App\Services\MemberInsights\CampaignAnalyticsService;

/**
 * Campaign Analytics API — serves the Angular analytics dashboard.
 *
 * Routes (prefix: /admin/api/campaign-analytics):
 *   GET /campaigns                    → list with headline stats
 *   GET /campaigns/{id}/summary       → KPIs + 30-day engagement series (T11)
 *   GET /campaigns/{id}/audiences     → delivery by audience (T12)
 *   GET /campaigns/{id}/blocks        → block click ranking (T13)
 *   GET /campaigns/{id}/variants      → A/B results (T14)
 *   GET /audiences                    → registered audience labels (T6)
 *
 * All endpoints:
 *   - require admin auth
 *   - are scoped to SiteContext::getId()
 *   - return JSON only, no view rendering
 */
class CampaignAnalyticsApiController extends Controller
{
    public function __construct(
        private readonly CampaignAnalyticsService   $analyticsService,
        private readonly CampaignRepository         $campaignRepository,
        private readonly CampaignDeliveryRepository $deliveryRepository,
        private readonly AudienceRegistry           $audienceRegistry,
        private readonly Auth                       $auth,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // Campaign list
    // =========================================================================

    public function campaigns(Request $request): JsonResponse
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $siteId = SiteContext::getId();
        $page = (int)$request->get('page', 1);
        $perPage = (int)$request->get('per_page', 20);

        $paginated = $this->campaignRepository->paginateForSite($siteId, $perPage, $page);

        $items = [];
        foreach ($paginated['data'] as $campaign) {
            $summary = $this->analyticsService->summarise($campaign->id);
            $items[] = [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'is_active' => (bool)$campaign->is_active,
                'channel' => $campaign->channel instanceof CampaignChannel
                    ? $campaign->channel->value
                    : (string)$campaign->channel,
                'priority' => $campaign->priority ?? 0,
                'created_at' => $campaign->created_at?->format('Y-m-d'),
                'deliveries' => $summary['deliveries'],
                'open_rate' => $summary['open_rate'],
                'click_rate' => $summary['click_rate'],
            ];
        }

        return $this->resourceResponse([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated['current_page'] ?? $page,
                'per_page' => $paginated['per_page'] ?? $perPage,
                'total' => $paginated['total'] ?? count($items),
                'last_page' => $paginated['last_page'] ?? 1,
            ],
        ]);
    }

    // =========================================================================
    // T11 — Summary
    // =========================================================================

    public function summary(int $campaignId): JsonResponse
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        $campaign = $this->campaignRepository->find($campaignId);

        if (!$campaign || $campaign->site_id !== SiteContext::getId()) {
            return $this->resourceResponse(['error' => 'Campaign not found'], 404);
        }

        $days = 30;
        $summary = $this->analyticsService->summarise($campaignId);
        $series = $this->analyticsService->getDailyEngagement($campaignId, $days);

        return $this->resourceResponse([
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'channel' => $campaign->channel instanceof CampaignChannel
                    ? $campaign->channel->value
                    : (string)$campaign->channel,
                'priority' => $campaign->priority ?? 0,
                'is_active' => (bool)$campaign->is_active,
            ],
            'summary' => $summary,
            'channel_split' => $this->buildChannelSplit($campaignId),
            'variant_count' => $this->countVariants($campaignId),
            'daily_series' => $series,   // 30-day opens + clicks for trend chart
        ]);
    }

    // =========================================================================
    // T12 — Audiences
    // =========================================================================

    private function buildChannelSplit(int $campaignId): array
    {
        $rows = \App\Models\CampaignDelivery::where('campaign_id', $campaignId)
            ->selectRaw('channel, COUNT(*) as count')
            ->groupBy('channel')
            ->get();

        $total = $rows->sum('count');

        return $rows->map(fn($row) => [
            'channel' => $row->channel,
            'count' => (int)$row->count,
            'percent' => $total > 0 ? round($row->count / $total * 100, 1) : 0.0,
        ])->toArray();
    }

    // =========================================================================
    // T13 — Blocks
    // =========================================================================

    private function countVariants(int $campaignId): int
    {
        return \App\Models\CampaignVariant::where('campaign_id', $campaignId)->count();
    }

    // =========================================================================
    // T14 — Variants
    // =========================================================================

    public function audiences(int $campaignId): JsonResponse
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $campaign = $this->campaignRepository->find($campaignId);

        if (!$campaign || $campaign->site_id !== SiteContext::getId()) {
            return $this->jsonResponse(['error' => 'Campaign not found'], 404);
        }

        $byAudience = $this->analyticsService->byAudience($campaignId);
        $labels = $this->audienceRegistry->labels();

        $result = [];
        foreach ($byAudience as $key => $data) {
            $result[] = array_merge($data, [
                'audience_key' => $key,
                'audience_label' => $labels[$key] ?? $key,
            ]);
        }

        usort($result, fn($a, $b) => $b['deliveries'] <=> $a['deliveries']);

        return $this->resourceResponse(['audiences' => $result]);
    }

    // =========================================================================
    // Audience list (T6)
    // =========================================================================

    public function blocks(int $campaignId): JsonResponse
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $campaign = $this->campaignRepository->find($campaignId);

        if (!$campaign || $campaign->site_id !== SiteContext::getId()) {
            return $this->jsonResponse(['error' => 'Campaign not found'], 404);
        }

        return $this->resourceResponse([
            'blocks' => $this->analyticsService->rankedBlocks($campaignId),
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    public function variants(int $campaignId): JsonResponse
    {
        if (!$this->auth->check()) {
            return $this->resourceResponse(['error' => 'Unauthorized'], 401);
        }

        $campaign = $this->campaignRepository->find($campaignId);

        if (!$campaign || $campaign->site_id !== SiteContext::getId()) {
            return $this->resourceResponse(['error' => 'Campaign not found'], 404);
        }

        $variants = \App\Models\CampaignVariant::where('campaign_id', $campaignId)
            ->orderBy('key')
            ->get();

        $result = [];
        foreach ($variants as $variant) {
            $deliveries = \App\Models\CampaignDelivery::where('campaign_id', $campaignId)
                ->where('variant_id', $variant->id)
                ->count();

            $uniqueOpens = \App\Models\CampaignEvent::where('campaign_id', $campaignId)
                ->where('variant_id', $variant->id)
                ->where('event_type', 'open')
                ->distinct('member_id')
                ->count('member_id');

            $uniqueClicks = \App\Models\CampaignEvent::where('campaign_id', $campaignId)
                ->where('variant_id', $variant->id)
                ->where('event_type', 'click')
                ->distinct('member_id')
                ->count('member_id');

            $result[] = [
                'variant_id' => $variant->id,
                'key' => $variant->key,
                'weight' => $variant->weight,
                'deliveries' => $deliveries,
                'unique_opens' => $uniqueOpens,
                'unique_clicks' => $uniqueClicks,
                'open_rate' => $deliveries > 0 ? round($uniqueOpens / $deliveries * 100, 2) : 0.0,
                'click_rate' => $deliveries > 0 ? round($uniqueClicks / $deliveries * 100, 2) : 0.0,
            ];
        }

        return $this->resourceResponse(['variants' => $result]);
    }

    public function audienceList(): JsonResponse
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $items = [];
        foreach ($this->audienceRegistry->labels() as $key => $label) {
            $items[] = ['key' => $key, 'label' => $label];
        }

        return $this->resourceResponse(['audiences' => $items]);
    }
}