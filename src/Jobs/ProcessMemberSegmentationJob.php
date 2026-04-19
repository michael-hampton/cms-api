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
use App\Services\MemberInsights\Campaigns\CampaignCooldownChecker;
use App\Services\MemberInsights\Campaigns\CampaignMatcher;
use App\Services\MemberInsights\Segmentation\MemberSegmentResolver;
use App\Services\MemberInsights\Segmentation\SegmentPersister;

/**
 * Orchestrates the full segmentation pipeline for a single member.
 *
 * Flow:
 *   1. Load profile snapshot
 *   2. Resolve matching segments from DB rules
 *   3. Persist segment assignments (idempotent upsert)
 *   4. Find active campaigns for those segments
 *   5. Dispatch SendCampaignJob for each eligible campaign (respects cooldown + cap)
 *
 * Intentionally does NOT send notifications directly — that is
 * SendCampaignJob's responsibility.
 *
 * Hard cap: MAX_CAMPAIGNS_PER_RUN prevents a member from receiving
 * a flood of campaigns in a single segmentation pass.
 */
class ProcessMemberSegmentationJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    private const MAX_CAMPAIGNS_PER_RUN = 3;
    public int $backoff = 60;
    private MemberSegmentResolver $resolver;
    private SegmentPersister $persister;
    private CampaignMatcher $matcher;
    private CampaignCooldownChecker $cooldown;
    private MemberRepository $memberRepository;
    private MemberSegmentationProfileRepository $profileRepository;
    private Dispatcher $dispatcher;

    public function __construct(
        public readonly int $memberId,
        public readonly int $siteId,
    )
    {
    }

    public function handle(): void
    {
        $member = $this->memberRepository->find($this->memberId);

        if ($member === null) {
            Logger::warning('ProcessMemberSegmentationJob: member not found', [
                'member_id' => $this->memberId,
                'site_id' => $this->siteId,
            ]);
            return;
        }

        $profile = $this->profileRepository->getLatestProfile($this->memberId, $this->siteId);

        if ($profile === null) {
            Logger::info('ProcessMemberSegmentationJob: no segmentation profile found, skipping', [
                'member_id' => $this->memberId,
                'site_id' => $this->siteId,
            ]);
            return;
        }

        $segments = $this->resolver->resolve($profile);

        $this->persister->persist($this->memberId, $this->siteId, $segments);

        if (empty($segments)) {
            return;
        }

        $campaigns = $this->matcher->match($segments);
        $dispatched = 0;

        foreach ($campaigns as $campaign) {
            if ($dispatched >= self::MAX_CAMPAIGNS_PER_RUN) {
                break;
            }

            if (!$this->cooldown->isEligible($this->memberId, $campaign)) {
                continue;
            }

            try {
                $this->dispatcher->dispatch(SendCampaignJob::for($this->memberId, $campaign->id, $campaign->segment->key))->dispatchNow();
                $dispatched++;
            } catch (\Exception $exception) {
                Logger::error('ProcessMemberSegmentationJob: failed to dispatch SendCampaignJob', [
                    'member_id' => $this->memberId,
                    'campaign_id' => $campaign->id,
                    'error' => $exception->getMessage(),
                ]);
            }


        }
    }
}
