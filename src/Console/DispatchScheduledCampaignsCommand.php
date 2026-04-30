<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\SendCampaignJob;
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\MemberInsights\Campaigns\CampaignSchedulerService;

class DispatchScheduledCampaignsCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;
    public $description = 'Dispatch SendCampaignJob for all campaigns whose scheduled_at has arrived.';
    protected $signature = 'campaigns:dispatch-scheduled';

    public function __construct(
        private readonly CampaignRepository       $campaignRepository,
        private readonly MemberRepository         $memberRepository,
        private readonly CampaignSchedulerService $schedulerService,
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('campaigns:dispatch-scheduled');

        $campaigns = $this->campaignRepository->findDueForDispatch();

        if ($campaigns->isEmpty()) {
            $this->reportResult($result);
            return self::SUCCESS;
        }

        foreach ($campaigns as $campaign) {
            try {
                $this->dispatchCampaign($campaign);

                $this->schedulerService->markSent($campaign->id);

                $result->incrementSucceeded();

            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to dispatch campaign {$campaign->id}: {$e->getMessage()}",
                    context: [
                        'campaign_id' => $campaign->id,
                        'scheduled_at' => $campaign->scheduled_at,
                    ],
                    throwable: $e
                );
            }
        }

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function dispatchCampaign(\App\Models\Campaign $campaign): void
    {
        $segmentId = $campaign->segment_id;

        $this->memberRepository->chunkActiveForSegment(
            segmentId: $segmentId,
            chunkSize: 200,
            callback: function (array $members) use ($campaign) {

                foreach ($members as $member) {
                    dispatch(SendCampaignJob::for(
                        memberId: $member->id,
                        campaignId: $campaign->id,
                        segmentKey: (string)$campaign->segment_id,
                        audienceKey: 'all_users',
                    ));
                }
            },
        );
    }
}