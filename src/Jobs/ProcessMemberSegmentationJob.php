<?php

namespace App\Jobs;

use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Repositories\MemberInsights\MemberSegmentationProfileRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\MemberInsights\Audiences\AudienceMatcher;
use App\Services\MemberInsights\Campaigns\CampaignCooldownChecker;
use App\Services\MemberInsights\Campaigns\CampaignMatcher;
use App\Services\MemberInsights\Campaigns\CampaignPriorityResolver;
use App\Services\MemberInsights\DeliveryRateLimiter;
use App\Services\MemberInsights\Segmentation\MemberSegmentResolver;
use App\Services\MemberInsights\Segmentation\SegmentPersister;

/**
 * Orchestrates the full segmentation pipeline for a single member.
 *
 * Flow:
 *   1.  Load member + profile snapshot
 *   2.  Resolve matching segments from DB rules
 *   3.  Persist segment assignments (idempotent upsert)
 *   4.  Resolve which audience keys the member belongs to (T6/T12)
 *   5.  Find active campaigns for those segments
 *   6.  Rank campaigns by priority (T10)
 *   7.  Guard: global daily marketing cap (T10)
 *   8.  Guard: per-campaign cooldown
 *   9.  Dispatch SendCampaignJob with audience key snapshot (T12)
 *
 * Hard cap: MAX_CAMPAIGNS_PER_RUN limits floods from a single segmentation pass.
 */
class ProcessMemberSegmentationJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    private const MAX_CAMPAIGNS_PER_RUN = 3;

    public int $backoff = 60;

    // All injected via the framework's job-dependency-injection mechanism.
    private MemberRepository $memberRepository;
    private MemberSegmentationProfileRepository $profileRepository;
    private MemberSegmentResolver $resolver;
    private SegmentPersister $persister;
    private CampaignMatcher $matcher;
    private CampaignCooldownChecker $cooldown;
    private CampaignPriorityResolver $priorityResolver;  // T10
    private DeliveryRateLimiter $rateLimiter;       // T10
    private AudienceMatcher $audienceMatcher;   // T6/T12
    private Dispatcher $dispatcher;

    public function __construct(
        public readonly int $memberId,
        public readonly int $siteId,
    )
    {
    }

    public function handle(): void
    {
        // ── 1. Load member ────────────────────────────────────────────────
        $member = $this->memberRepository->find($this->memberId);

        if ($member === null) {
            Logger::warning('ProcessMemberSegmentationJob: member not found', [
                'member_id' => $this->memberId,
                'site_id' => $this->siteId,
            ]);
            return;
        }

        // ── 2. Load profile ───────────────────────────────────────────────
        $profile = $this->profileRepository->getLatestProfile($this->memberId, $this->siteId);

        if ($profile === null) {
            Logger::info('ProcessMemberSegmentationJob: no profile found, skipping', [
                'member_id' => $this->memberId,
                'site_id' => $this->siteId,
            ]);
            return;
        }

        // ── 3. Resolve + persist segments ─────────────────────────────────
        $segments = $this->resolver->resolve($profile);
        $this->persister->persist($this->memberId, $this->siteId, $segments);

        if (empty($segments)) {
            return;
        }

        // ── 4. Resolve audiences for this member (T6/T12 snapshot) ────────
        //
        // AudienceMatcher resolves every audience key the member currently
        // belongs to.  We pick the most specific one to pass to SendCampaignJob
        // so CampaignDelivery.audience_key reflects the real targeting context.
        //
        // Priority order: most specific audience first (non-universal wins).
        $audienceKeys = $this->audienceMatcher->resolveAll($member, $profile);
        $primaryAudience = $this->resolvePrimaryAudienceKey($audienceKeys);

        // ── 5. Match campaigns ────────────────────────────────────────────
        $campaigns = $this->matcher->match($segments);

        if ($campaigns->isEmpty()) {
            return;
        }

        // ── 6. Rank by priority (T10) ─────────────────────────────────────
        $rankedCampaigns = $this->priorityResolver->rank($campaigns);

        // ── 7. Global daily marketing cap (T10) ───────────────────────────
        if (!$this->rateLimiter->isUnderDailyMarketingCap($this->memberId)) {
            Logger::info('ProcessMemberSegmentationJob: daily marketing cap reached, skipping all campaigns', [
                'member_id' => $this->memberId,
                'site_id' => $this->siteId,
                'cap' => $this->rateLimiter->dailyMarketingCap(),
            ]);
            return;
        }

        // ── 8 + 9. Per-campaign cooldown + dispatch ────────────────────────
        $dispatched = 0;

        foreach ($rankedCampaigns as $campaign) {
            if ($dispatched >= self::MAX_CAMPAIGNS_PER_RUN) {
                break;
            }

            if (!$this->cooldown->isEligible($this->memberId, $campaign)) {
                Logger::info('ProcessMemberSegmentationJob: campaign in cooldown, skipping', [
                    'member_id' => $this->memberId,
                    'campaign_id' => $campaign->id,
                ]);
                continue;
            }

            try {
                $this->dispatcher->dispatch(
                    SendCampaignJob::for(
                        memberId: $this->memberId,
                        campaignId: $campaign->id,
                        segmentKey: $campaign->segment->key,
                        audienceKey: $primaryAudience,   // T12 snapshot
                    )
                );
                $dispatched++;

            } catch (\Exception $e) {
                Logger::error('ProcessMemberSegmentationJob: failed to dispatch SendCampaignJob', [
                    'member_id' => $this->memberId,
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Pick the most descriptive audience key from the resolved list.
     *
     * 'all_users' is a catch-all and is only used when nothing more specific
     * matched.  This ensures the analytics audience breakdown (T12) shows
     * meaningful segments rather than every row being 'all_users'.
     */
    private function resolvePrimaryAudienceKey(array $audienceKeys): string
    {
        $filtered = array_filter($audienceKeys, fn($k) => $k !== 'all_users');

        return !empty($filtered)
            ? array_values($filtered)[0]
            : 'all_users';
    }
}