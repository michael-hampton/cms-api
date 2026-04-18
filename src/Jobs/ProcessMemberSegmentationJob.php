<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\MemberProfileSnapshot;
use App\Services\Campaigns\CampaignCooldownChecker;
use App\Services\Campaigns\CampaignMatcher;
use App\Services\Segmentation\MemberSegmentResolver;
use App\Services\Segmentation\SegmentPersister;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
class ProcessMemberSegmentationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_CAMPAIGNS_PER_RUN = 3;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $memberId,
        public readonly int $siteId,
    )
    {
    }

    public function handle(
        MemberSegmentResolver   $resolver,
        SegmentPersister        $persister,
        CampaignMatcher         $matcher,
        CampaignCooldownChecker $cooldown,
    ): void
    {
        $member = Member::find($this->memberId);

        if ($member === null) {
            Log::warning('ProcessMemberSegmentationJob: member not found', [
                'member_id' => $this->memberId,
                'site_id' => $this->siteId,
            ]);
            return;
        }

        $snapshot = MemberProfileSnapshot::where('member_id', $this->memberId)
            ->where('site_id', $this->siteId)
            ->latest('built_at')
            ->first();

        if ($snapshot === null) {
            Log::info('ProcessMemberSegmentationJob: no profile snapshot found, skipping', [
                'member_id' => $this->memberId,
                'site_id' => $this->siteId,
            ]);
            return;
        }

        $profile = $snapshot->data;
        $segments = $resolver->resolve($profile);

        $persister->persist($this->memberId, $this->siteId, $segments);

        if (empty($segments)) {
            return;
        }

        $campaigns = $matcher->match($segments);
        $dispatched = 0;

        foreach ($campaigns as $campaign) {
            if ($dispatched >= self::MAX_CAMPAIGNS_PER_RUN) {
                break;
            }

            if (!$cooldown->isEligible($this->memberId, $campaign)) {
                continue;
            }

            SendCampaignJob::dispatch($this->memberId, $campaign->id, $campaign->segment->key);
            $dispatched++;
        }
    }
}