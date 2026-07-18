<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Enums\Member\CampaignPurpose;
use App\Enums\Subscriptions\CommunicationSuppressionReason;
use App\Models\Member;
use App\Models\SubscriptionCommunication;
use App\Services\Members\Consents\ConsentQueryService;
use App\Services\Subscriptions\Communications\CommunicationConsentGate;
use Mockery;
use PHPUnit\Framework\TestCase;

class CommunicationConsentGateTest extends TestCase
{
    private ConsentQueryService $consentQuery;
    private CommunicationConsentGate $gate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consentQuery = Mockery::mock(ConsentQueryService::class);
        $this->gate = new CommunicationConsentGate($this->consentQuery);
    }

    public function test_blocks_when_no_member(): void
    {
        $communication = $this->makeCommunication(CampaignPurpose::TRANSACTIONAL);

        $this->assertSame(
            CommunicationSuppressionReason::NO_MEMBER,
            $this->gate->evaluate($communication, null),
        );
    }

    public function test_blocks_when_member_deceased_regardless_of_purpose(): void
    {
        $communication = $this->makeCommunication(CampaignPurpose::TRANSACTIONAL);
        $member = $this->makeMember(isDeceased: true);

        $this->assertSame(
            CommunicationSuppressionReason::MEMBER_DECEASED,
            $this->gate->evaluate($communication, $member),
        );
    }

    public function test_transactional_communications_bypass_marketing_consent(): void
    {
        $communication = $this->makeCommunication(CampaignPurpose::TRANSACTIONAL);
        $member = $this->makeMember();

        $this->consentQuery->shouldReceive('hasConsent')->never();

        $this->assertNull($this->gate->evaluate($communication, $member));
    }

    public function test_marketing_communication_blocked_when_member_is_minor(): void
    {
        $communication = $this->makeCommunication(CampaignPurpose::MARKETING);
        $member = $this->makeMember(isMinor: true);

        $this->consentQuery->shouldReceive('hasConsent')->never();

        $this->assertSame(
            CommunicationSuppressionReason::MINOR_MARKETING_EXCLUDED,
            $this->gate->evaluate($communication, $member),
        );
    }

    public function test_marketing_communication_blocked_when_consent_not_given(): void
    {
        $communication = $this->makeCommunication(CampaignPurpose::MARKETING);
        $member = $this->makeMember();

        $this->consentQuery->shouldReceive('hasConsent')->once()->with($member, 'marketing_email')->andReturn(false);

        $this->assertSame(
            CommunicationSuppressionReason::MARKETING_CONSENT_NOT_GIVEN,
            $this->gate->evaluate($communication, $member),
        );
    }

    public function test_marketing_communication_allowed_when_consent_given(): void
    {
        $communication = $this->makeCommunication(CampaignPurpose::MARKETING);
        $member = $this->makeMember();

        $this->consentQuery->shouldReceive('hasConsent')->once()->andReturn(true);

        $this->assertNull($this->gate->evaluate($communication, $member));
    }

    public function test_marketing_communication_blocked_when_consent_query_throws(): void
    {
        $communication = $this->makeCommunication(CampaignPurpose::MARKETING);
        $member = $this->makeMember();

        $this->consentQuery->shouldReceive('hasConsent')->once()->andThrow(new \RuntimeException('missing type'));

        $this->assertSame(
            CommunicationSuppressionReason::MARKETING_CONSENT_NOT_GIVEN,
            $this->gate->evaluate($communication, $member),
        );
    }

    public function test_channel_check_blocks_letter_when_do_not_mail_set(): void
    {
        $member = $this->makeMember(doNotMail: true);

        $this->assertSame(
            CommunicationSuppressionReason::DO_NOT_MAIL,
            $this->gate->evaluateChannel($member, 'letter'),
        );
    }

    public function test_channel_check_does_not_block_email_when_do_not_mail_set(): void
    {
        $member = $this->makeMember(doNotMail: true);

        $this->assertNull($this->gate->evaluateChannel($member, 'email'));
    }

    public function test_channel_check_allows_letter_when_do_not_mail_not_set(): void
    {
        $member = $this->makeMember();

        $this->assertNull($this->gate->evaluateChannel($member, 'letter'));
    }

    private function makeCommunication(CampaignPurpose $purpose): SubscriptionCommunication
    {
        $communication = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $communication->purpose = $purpose;
        return $communication;
    }

    private function makeMember(
        bool $isDeceased = false,
        bool $isMinor = false,
        bool $doNotMail = false,
    ): Member {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->is_deceased = $isDeceased;
        $member->is_minor = $isMinor;
        $member->do_not_mail = $doNotMail;
        return $member;
    }
}
