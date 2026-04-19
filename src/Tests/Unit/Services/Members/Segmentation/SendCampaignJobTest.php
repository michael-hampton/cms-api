<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Enums\Member\CampaignChannel;
use App\Enums\Member\CampaignPurpose;
use App\Framework\Container;
use App\Framework\Notifications\NotificationDispatcher;
use App\Jobs\SendCampaignJob;
use App\Mail\Campaigns\WeMissYouMail;
use App\Models\Campaign;
use App\Models\Member;
use App\Repositories\MemberInsights\CampaignRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\MemberInsights\Campaigns\CampaignConsentChecker;
use App\Services\MemberInsights\Campaigns\CampaignExecutionLogger;
use App\Services\MemberInsights\Campaigns\CampaignNotification;
use App\Services\MemberInsights\Segmentation\ChannelResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class SendCampaignJobTest extends FunctionalTestCase
{
    private ChannelResolver|MockInterface $channelResolver;
    private CampaignConsentChecker|MockInterface $consentChecker;
    private NotificationDispatcher|MockInterface $dispatcher;
    private CampaignExecutionLogger|MockInterface $logger;
    private MemberRepository|MockInterface $memberRepository;
    private CampaignRepository|MockInterface $campaignRepository;

    public function test_does_nothing_when_member_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(999)->andReturnNull();

        $this->channelResolver->shouldNotReceive('resolveChannels');
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->runJob(memberId: 999);
        $this->addToAssertionCount(1);
    }

    private function runJob(int $memberId = 1, int $campaignId = 1): void
    {
        $job = new SendCampaignJob($memberId, $campaignId, 'churning');
        $job->__wakeup();
        $job->handle();
    }

    // =========================================================================
    // Guard clauses
    // =========================================================================

    public function test_does_nothing_when_campaign_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($this->makeMember(1));
        $this->campaignRepository->shouldReceive('find')->with(999, ['segment'])->andReturnNull();

        $this->channelResolver->shouldNotReceive('resolveChannels');

        $this->runJob(campaignId: 999);
        $this->addToAssertionCount(1);
    }

    private function makeMember(int $id): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;
        $member->email = 'member@example.com';
        return $member;
    }

    public function test_does_nothing_when_campaign_is_inactive(): void
    {
        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($this->makeMember(1));

        $campaign = $this->makeCampaign();
        $campaign->is_active = false;
        $this->campaignRepository->shouldReceive('find')->with(1, ['segment'])->andReturn($campaign);

        $this->channelResolver->shouldNotReceive('resolveChannels');

        $this->runJob();
        $this->addToAssertionCount(1);
    }

    private function makeCampaign(): Campaign
    {
        $segment = (object)['key' => 'churning'];

        $campaign = Mockery::mock(Campaign::class)->makePartial();
        $campaign->id = 1;
        $campaign->is_active = true;
        $campaign->template = WeMissYouMail::class;
        $campaign->purpose = CampaignPurpose::MARKETING;
        $campaign->channel = CampaignChannel::EMAIL;
        $campaign->fallback_channels = [];
        $campaign->setRelation('segment', $segment);

        return $campaign;
    }

    // =========================================================================
    // Consent enforcement
    // =========================================================================

    public function test_fails_permanently_when_mailable_class_does_not_exist(): void
    {
        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($this->makeMember(1));

        $campaign = $this->makeCampaign();
        $campaign->template = 'App\Mail\Campaigns\NonExistentMail';
        $this->campaignRepository->shouldReceive('find')->with(1, ['segment'])->andReturn($campaign);

        $this->channelResolver->shouldNotReceive('resolveChannels');
        $this->dispatcher->shouldNotReceive('dispatch');

        // Job calls $this->fail() — we verify it doesn't dispatch or log
        $this->runJob();
        $this->addToAssertionCount(1);
    }

    public function test_dispatches_notification_when_primary_channel_has_consent(): void
    {
        $member = $this->makeMember(1);
        $campaign = $this->makeCampaign();

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->campaignRepository->shouldReceive('find')->with(1, ['segment'])->andReturn($campaign);

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->once()
            ->with($campaign)
            ->andReturn([CampaignChannel::EMAIL]);

        $this->consentChecker
            ->shouldReceive('canSend')
            ->once()
            ->with($member, CampaignPurpose::MARKETING, CampaignChannel::EMAIL)
            ->andReturn(true);

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(CampaignNotification::class))
            ->andReturn(1);

        $this->logger
            ->shouldReceive('log')
            ->once()
            ->with(1, $campaign, 'churning');

        $this->runJob();
        $this->addToAssertionCount(1);
    }

    public function test_skips_silently_when_all_channels_blocked_by_consent(): void
    {
        $member = $this->makeMember(1);
        $campaign = $this->makeCampaign();

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->campaignRepository->shouldReceive('find')->with(1, ['segment'])->andReturn($campaign);

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->once()
            ->andReturn([CampaignChannel::EMAIL, CampaignChannel::PUSH]);

        $this->consentChecker
            ->shouldReceive('canSend')
            ->twice()
            ->andReturn(false);

        $this->dispatcher->shouldNotReceive('dispatch');
        $this->logger->shouldNotReceive('log');

        $this->runJob();
        $this->addToAssertionCount(1);
    }

    public function test_falls_back_to_second_channel_when_primary_is_consent_blocked(): void
    {
        $member = $this->makeMember(1);
        $campaign = $this->makeCampaign();

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->campaignRepository->shouldReceive('find')->with(1, ['segment'])->andReturn($campaign);

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->once()
            ->andReturn([CampaignChannel::EMAIL, CampaignChannel::PUSH]);

        $this->consentChecker
            ->shouldReceive('canSend')
            ->with($member, CampaignPurpose::MARKETING, CampaignChannel::EMAIL)
            ->once()
            ->andReturn(false);

        $this->consentChecker
            ->shouldReceive('canSend')
            ->with($member, CampaignPurpose::MARKETING, CampaignChannel::PUSH)
            ->once()
            ->andReturn(true);

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->andReturn(1);

        $this->logger->shouldReceive('log')->once();

        $this->runJob();
        $this->addToAssertionCount(1);
    }

    public function test_does_not_log_when_dispatcher_returns_zero_successes(): void
    {
        $member = $this->makeMember(1);
        $campaign = $this->makeCampaign();

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->campaignRepository->shouldReceive('find')->with(1, ['segment'])->andReturn($campaign);

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->andReturn([CampaignChannel::EMAIL]);

        $this->consentChecker
            ->shouldReceive('canSend')
            ->andReturn(true);

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->andReturn(0); // nothing sent

        $this->logger->shouldNotReceive('log');

        $this->runJob();
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_stops_after_first_successful_channel_does_not_try_remaining(): void
    {
        $member = $this->makeMember(1);
        $campaign = $this->makeCampaign();

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->campaignRepository->shouldReceive('find')->with(1, ['segment'])->andReturn($campaign);

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->andReturn([CampaignChannel::EMAIL, CampaignChannel::PUSH]);

        // Email passes — push must never be evaluated
        $this->consentChecker
            ->shouldReceive('canSend')
            ->once() // only called once
            ->with($member, CampaignPurpose::MARKETING, CampaignChannel::EMAIL)
            ->andReturn(true);

        $this->dispatcher->shouldReceive('dispatch')->once()->andReturn(1);
        $this->logger->shouldReceive('log')->once();

        $this->runJob();
        $this->addToAssertionCount(1);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->channelResolver = Mockery::mock(ChannelResolver::class);
        $this->consentChecker = Mockery::mock(CampaignConsentChecker::class);
        $this->dispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->logger = Mockery::mock(CampaignExecutionLogger::class);
        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->campaignRepository = Mockery::mock(CampaignRepository::class);

        Container::getInstance()->instance(ChannelResolver::class, $this->channelResolver);
        Container::getInstance()->instance(CampaignConsentChecker::class, $this->consentChecker);
        Container::getInstance()->instance(NotificationDispatcher::class, $this->dispatcher);
        Container::getInstance()->instance(CampaignExecutionLogger::class, $this->logger);
        Container::getInstance()->instance(MemberRepository::class, $this->memberRepository);
        Container::getInstance()->instance(CampaignRepository::class, $this->campaignRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
