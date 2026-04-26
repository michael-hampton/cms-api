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
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\MemberInsights\CampaignDeliveryRepository;
use App\Repositories\MemberInsights\CampaignVariantAssigner;
use App\Repositories\Members\MemberRepository;
use App\Services\MemberInsights\Campaigns\CampaignConsentChecker;
use App\Services\MemberInsights\Campaigns\CampaignExecutionLogger;
use App\Services\MemberInsights\Campaigns\CampaignNotification;
use App\Services\MemberInsights\InAppNotificationDispatcher;
use App\Services\MemberInsights\Segmentation\SmartChannelResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class SendCampaignJobTest extends FunctionalTestCase
{
    private SmartChannelResolver|MockInterface $channelResolver;
    private CampaignConsentChecker|MockInterface $consentChecker;
    private NotificationDispatcher|MockInterface $dispatcher;
    private CampaignExecutionLogger|MockInterface $logger;
    private MemberRepository|MockInterface $memberRepository;
    private CampaignRepository|MockInterface $campaignRepository;
    private CampaignVariantAssigner|MockInterface $variantAssigner;
    private CampaignDeliveryRepository|MockInterface $deliveryRepository;
    private InAppNotificationDispatcher|MockInterface $webPushDispatcher;

    public function test_does_nothing_when_member_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(999)->once()->andReturnNull();

        $this->channelResolver->shouldNotReceive('resolveChannels');
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->runJob(memberId: 999);
        $this->addToAssertionCount(1);
    }

    private function makeDelivery(int $id = 123, string $token = 'test-token'): \App\Models\CampaignDelivery
    {
        $delivery = Mockery::mock(\App\Models\CampaignDelivery::class)->makePartial();
        $delivery->id = $id;
        $delivery->token = $token;
        return $delivery;
    }

    private function runJob(int $memberId = 1, int $campaignId = 1): void
    {
        $job = new SendCampaignJob($memberId, $campaignId, 'churning');

        // Use reflection to set the properties because they are not initialized
        $reflection = new \ReflectionClass(SendCampaignJob::class);
        $properties = [
            'channelResolver' => $this->channelResolver,
            'consentChecker' => $this->consentChecker,
            'notificationDispatcher' => $this->dispatcher,
            'executionLogger' => $this->logger,
            'memberRepository' => $this->memberRepository,
            'campaignRepository' => $this->campaignRepository,
            'variantAssigner' => $this->variantAssigner,
            'deliveryRepository' => $this->deliveryRepository,
            'webPushDispatcher' => $this->webPushDispatcher,
        ];

        foreach ($properties as $name => $value) {
            $prop = $reflection->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue($job, $value);
        }

        $job->handle();
    }

    // =========================================================================
    // Guard clauses
    // =========================================================================

    public function test_does_nothing_when_campaign_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(1)->once()->andReturn($this->makeMember(1));
        $this->campaignRepository->shouldReceive('find')->with(999, ['segment'])->once()->andReturnNull();

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
        $this->memberRepository->shouldReceive('find')->with(1)->once()->andReturn($this->makeMember(1));

        $campaign = $this->makeCampaign();
        $campaign->is_active = false;
        $this->campaignRepository->shouldReceive('find')->with(1, ['segment'])->once()->andReturn($campaign);

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
        $campaign->purpose = 'marketing';
        $campaign->channel = 'email';
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

        $this->variantAssigner->shouldReceive('assignVariant')->andReturnNull();

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

        $this->variantAssigner->shouldReceive('assignVariant')->once()->andReturnNull();

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->once()
            ->with(1, $campaign)
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

        $this->deliveryRepository
            ->shouldReceive('record')
            ->once()
            ->with(1, 1, 'email', 'all_users', null)
            ->andReturn($this->makeDelivery());

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

        $this->variantAssigner->shouldReceive('assignVariant')->once()->andReturnNull();

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->once()
            ->with(1, $campaign)
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
            ->andReturn(false);

        $this->dispatcher->shouldNotReceive('dispatch');
        $this->webPushDispatcher->shouldNotReceive('dispatch');
        $this->deliveryRepository->shouldNotReceive('record');
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

        $this->variantAssigner->shouldReceive('assignVariant')->once()->andReturnNull();

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->once()
            ->with(1, $campaign)
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

        $this->webPushDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->andReturn(1);

        $this->deliveryRepository
            ->shouldReceive('record')
            ->once()
            ->with(1, 1, 'push', 'all_users', null)
            ->andReturn($this->makeDelivery());

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

        $this->variantAssigner->shouldReceive('assignVariant')->once()->andReturnNull();

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->once()
            ->with(1, $campaign)
            ->andReturn([CampaignChannel::EMAIL]);

        $this->consentChecker
            ->shouldReceive('canSend')
            ->once()
            ->andReturn(true);

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->andReturn(0); // nothing sent

        $this->deliveryRepository
            ->shouldReceive('record')
            ->once()
            ->andReturn($this->makeDelivery());

        $this->deliveryRepository
            ->shouldReceive('delete')
            ->once()
            ->with(123);

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

        $this->variantAssigner->shouldReceive('assignVariant')->once()->andReturnNull();

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->once()
            ->with(1, $campaign)
            ->andReturn([CampaignChannel::EMAIL]);

        $this->consentChecker
            ->shouldReceive('canSend')
            ->once()
            ->andReturn(true);

        $this->dispatcher->shouldReceive('dispatch')->once()->andReturn(1);
        $this->deliveryRepository->shouldReceive('record')->once()->andReturn($this->makeDelivery());
        $this->logger->shouldReceive('log')->once();

        $this->runJob();
        $this->addToAssertionCount(1);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->channelResolver = Mockery::mock(SmartChannelResolver::class);
        $this->consentChecker = Mockery::mock(CampaignConsentChecker::class);
        $this->dispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->logger = Mockery::mock(CampaignExecutionLogger::class);
        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->campaignRepository = Mockery::mock(CampaignRepository::class);
        $this->variantAssigner = Mockery::mock(CampaignVariantAssigner::class);
        $this->deliveryRepository = Mockery::mock(CampaignDeliveryRepository::class);
        $this->webPushDispatcher = Mockery::mock(InAppNotificationDispatcher::class);

        Container::getInstance()->instance(SmartChannelResolver::class, $this->channelResolver);
        Container::getInstance()->instance(CampaignConsentChecker::class, $this->consentChecker);
        Container::getInstance()->instance(NotificationDispatcher::class, $this->dispatcher);
        Container::getInstance()->instance(CampaignExecutionLogger::class, $this->logger);
        Container::getInstance()->instance(MemberRepository::class, $this->memberRepository);
        Container::getInstance()->instance(CampaignRepository::class, $this->campaignRepository);
        Container::getInstance()->instance(CampaignVariantAssigner::class, $this->variantAssigner);
        Container::getInstance()->instance(CampaignDeliveryRepository::class, $this->deliveryRepository);
        Container::getInstance()->instance(InAppNotificationDispatcher::class, $this->webPushDispatcher);
    }

    public function test_dispatches_notification_uses_template(): void
    {
        $member = $this->makeMember(1);
        $campaign = $this->makeCampaign();
        $campaign->template = 1;

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->campaignRepository->shouldReceive('find')->with(1, ['segment'])->andReturn($campaign);

        $this->variantAssigner->shouldReceive('assignVariant')->once()->andReturnNull();

        $this->channelResolver
            ->shouldReceive('resolveChannels')
            ->once()
            ->with(1, $campaign)
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

        $this->deliveryRepository
            ->shouldReceive('record')
            ->once()
            ->with(1, 1, 'email', 'all_users', null)
            ->andReturn($this->makeDelivery());

        $this->logger
            ->shouldReceive('log')
            ->once()
            ->with(1, $campaign, 'churning');

        $this->runJob();
        $this->addToAssertionCount(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
