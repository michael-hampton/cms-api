<?php

namespace App\Tests\Unit\Services\MemberInsights\Campaigns;

use App\Enums\Campaigns\CampaignScheduleStatus;
use App\Models\Campaign;
use App\Repositories\Cms\CampaignRepository;
use App\Services\MemberInsights\Campaigns\CampaignSchedulerService;
use PHPUnit\Framework\TestCase;

class CampaignSchedulerServiceTest extends TestCase
{
    private CampaignRepository $campaignRepository;
    private CampaignSchedulerService $service;

    public function test_schedule_sets_scheduled_at_and_status(): void
    {
        $future = new \DateTime('+1 hour');

        $campaign = $this->makeCampaign(1);

        $this->campaignRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($campaign);

        $this->campaignRepository
            ->shouldReceive('update')
            ->once()
            ->withArgs(function ($id, $data) use ($future) {
                return $data['schedule_status'] === CampaignScheduleStatus::Scheduled->value
                    && $data['scheduled_at'] == $future;
            })
            ->andReturnUsing(function ($id, $data) use ($campaign) {

                // simulate what real repository does
                $campaign->schedule_status = $data['schedule_status'];
                $campaign->scheduled_at = $data['scheduled_at'];

                return $campaign;
            });

        $result = $this->service->schedule(1, $future);

        $this->assertSame(
            CampaignScheduleStatus::Scheduled->value,
            $result->schedule_status
        );

        $this->assertSame(
            $future->format('Y-m-d H:i:s'),
            $result->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    // ── schedule() ────────────────────────────────────────────────────────────

    private function makeCampaign(int $id): Campaign
    {
        $campaign = mock(Campaign::class)->makePartial();
        $campaign->id = $id;

        return $campaign;
    }

    public function test_schedule_rejects_past_datetime(): void
    {
        $campaign = $this->makeCampaign(1);
        $this->campaignRepository->shouldReceive('find')->andReturn($campaign);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('scheduled_at must be in the future');

        $this->service->schedule(1, new \DateTime('-1 second'));
    }

    public function test_schedule_rejects_already_sent_campaign(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = CampaignScheduleStatus::Sent->value;

        $this->campaignRepository->shouldReceive('find')->andReturn($campaign);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already been sent');

        $this->service->schedule(1, new \DateTime('+1 hour'));
    }

    public function test_schedule_throws_when_campaign_not_found(): void
    {
        $this->campaignRepository->shouldReceive('find')->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->schedule(999, new \DateTime('+1 hour'));
    }

    // ── pauseSchedule() ───────────────────────────────────────────────────────

    public function test_pause_sets_status_to_paused(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = CampaignScheduleStatus::Scheduled->value;

        $this->campaignRepository
            ->shouldReceive('find')
            ->andReturn($campaign);

        $this->campaignRepository
            ->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($id, $data) use ($campaign) {
                $campaign->schedule_status = $data['schedule_status'];
                return $campaign;
            });

        $result = $this->service->pauseSchedule(1);

        $this->assertSame(CampaignScheduleStatus::Paused->value, $result->schedule_status);
    }

    public function test_pause_rejects_non_scheduled_campaign(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = CampaignScheduleStatus::Sent->value;

        $this->campaignRepository->shouldReceive('find')->andReturn($campaign);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only a scheduled campaign can be paused');

        $this->service->pauseSchedule(1);
    }

    public function test_pause_rejects_campaign_with_no_schedule(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = null;

        $this->campaignRepository->shouldReceive('find')->andReturn($campaign);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->pauseSchedule(1);
    }

    // ── resumeSchedule() ──────────────────────────────────────────────────────

    public function test_resume_sets_status_back_to_scheduled(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = CampaignScheduleStatus::Paused->value;

        $this->campaignRepository
            ->shouldReceive('find')
            ->andReturn($campaign);

        $this->campaignRepository
            ->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($id, $data) use ($campaign) {
                $campaign->schedule_status = $data['schedule_status'];
                return $campaign;
            });

        $result = $this->service->resumeSchedule(1);

        $this->assertSame(CampaignScheduleStatus::Scheduled->value, $result->schedule_status);
    }

    public function test_resume_rejects_non_paused_campaign(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = CampaignScheduleStatus::Scheduled->value;

        $this->campaignRepository->shouldReceive('find')->andReturn($campaign);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only a paused campaign can be resumed');

        $this->service->resumeSchedule(1);
    }

    // ── markSent() ────────────────────────────────────────────────────────────

    public function test_mark_sent_sets_status_to_sent(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = CampaignScheduleStatus::Scheduled->value;

        $this->campaignRepository
            ->shouldReceive('find')
            ->andReturn($campaign);

        $this->campaignRepository
            ->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($id, $data) use ($campaign) {
                $campaign->schedule_status = $data['schedule_status'];
                return $campaign;
            });

        $result = $this->service->markSent(1);

        $this->assertSame(CampaignScheduleStatus::Sent->value, $result->schedule_status);
    }

    // ── Campaign model helpers ────────────────────────────────────────────────

    public function test_campaign_is_due_for_dispatch_when_scheduled_and_past(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = CampaignScheduleStatus::Scheduled->value;
        $campaign->scheduled_at = new \DateTime('-1 minute');

        $this->assertTrue($campaign->isDueForDispatch());
    }

    public function test_campaign_is_not_due_when_scheduled_in_future(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = CampaignScheduleStatus::Scheduled->value;
        $campaign->scheduled_at = new \DateTime('+1 hour');

        $this->assertFalse($campaign->isDueForDispatch());
    }

    public function test_campaign_is_not_due_when_paused(): void
    {
        $campaign = $this->makeCampaign(1);
        $campaign->schedule_status = CampaignScheduleStatus::Paused->value;
        $campaign->scheduled_at = new \DateTime('-1 minute');

        $this->assertFalse($campaign->isDueForDispatch());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaignRepository = mock(CampaignRepository::class)->makePartial();
        $this->service = new CampaignSchedulerService($this->campaignRepository);
    }
}