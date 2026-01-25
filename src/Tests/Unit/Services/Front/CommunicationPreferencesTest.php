<?php

namespace App\Tests\Unit\Services\Front;

use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Subscriptions\MemberSubscriptionService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class CommunicationPreferencesTest extends FunctionalTestCase
{
    private $memberRepository;
    private $preferenceRepository;
    private $subscriberRepository;
    private MemberSubscriptionService $service;

    public function test_member_can_update_communication_preferences(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->communication_preferences = [];

        $this->memberRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($member);

        $member->shouldReceive('updateCommunicationPreferences')
            ->with([
                'marketing_emails' => true,
                'special_offers' => false,
                'third_party_communications' => false
            ])
            ->once()
            ->andReturn(true);

        $result = $this->service->updateCommunicationPreferences(1, [
            'marketing_emails' => true,
            'special_offers' => false,
            'third_party_communications' => false
        ]);

        $this->assertNotNull($result);
        $this->assertInstanceOf(Member::class, $result);
    }

    public function test_marketing_emails_can_be_blocked(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->communication_preferences = ['marketing_emails' => false];

        $member->shouldReceive('wantsMarketingEmails')
            ->andReturn(false);
        $result = $this->service->shouldReceiveMarketingEmail($member, 'marketing');

        $this->assertFalse($result);
    }

    public function test_transactional_emails_always_sent(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->communication_preferences = ['marketing_emails' => false];

        $result = $this->service->shouldReceiveMarketingEmail($member, 'transactional');

        $this->assertTrue($result);
    }

    public function test_special_offers_respect_preference(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->communication_preferences = [
            'marketing_emails' => true,
            'special_offers' => false
        ];

        $member->shouldReceive('wantsMarketingEmails')
            ->andReturn(true);
        $member->shouldReceive('wantsSpecialOffers')
            ->andReturn(false);

        $result = $this->service->shouldReceiveMarketingEmail($member, 'special_offer');

        $this->assertFalse($result);
    }

    public function test_third_party_communications_respect_preference(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->communication_preferences = [
            'marketing_emails' => true,
            'third_party_communications' => false
        ];

        $member->shouldReceive('wantsMarketingEmails')
            ->andReturn(true);
        $member->shouldReceive('wantsThirdPartyCommunications')
            ->andReturn(false);

        $result = $this->service->shouldReceiveMarketingEmail($member, 'third_party');

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = m::mock(MemberRepository::class);
        $this->preferenceRepository = m::mock(MemberSubscriptionPreferenceRepository::class);
        $this->subscriberRepository = m::mock(SubscriberRepository::class);

        $this->service = new MemberSubscriptionService(
            $this->memberRepository,
            $this->preferenceRepository,
            $this->subscriberRepository
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}