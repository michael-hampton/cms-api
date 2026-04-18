<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Enums\Member\CampaignChannel;
use App\Enums\Member\CampaignPurpose;
use App\Models\Member;
use App\Services\Members\Consents\ConsentQueryService;
use App\Services\Members\Segmentation\CampaignConsentChecker;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CampaignConsentCheckerTest extends TestCase
{
    private ConsentQueryService|MockInterface $consentQuery;
    private CampaignConsentChecker $checker;

    public function test_transactional_always_returns_true_without_querying_consent(): void
    {
        $this->consentQuery->shouldNotReceive('hasConsent');

        $result = $this->checker->canSend(
            $this->makeMember(),
            CampaignPurpose::TRANSACTIONAL,
            CampaignChannel::EMAIL,
        );

        $this->assertTrue($result);
    }

    // =========================================================================
    // Transactional bypass
    // =========================================================================

    private function makeMember(): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        return $member;
    }

    // =========================================================================
    // Marketing — email
    // =========================================================================

    public function test_marketing_email_allowed_when_member_has_marketing_email_consent(): void
    {
        $member = $this->makeMember();

        $this->consentQuery
            ->shouldReceive('hasConsent')
            ->once()
            ->with($member, 'marketing_email')
            ->andReturn(true);

        $this->assertTrue(
            $this->checker->canSend($member, CampaignPurpose::MARKETING, CampaignChannel::EMAIL)
        );
    }

    public function test_marketing_email_blocked_when_no_consent(): void
    {
        $member = $this->makeMember();

        $this->consentQuery
            ->shouldReceive('hasConsent')
            ->once()
            ->with($member, 'marketing_email')
            ->andReturn(false);

        $this->assertFalse(
            $this->checker->canSend($member, CampaignPurpose::MARKETING, CampaignChannel::EMAIL)
        );
    }

    // =========================================================================
    // Marketing — push (maps to marketing_email until push-specific type exists)
    // =========================================================================

    public function test_marketing_push_allowed_when_member_has_marketing_email_consent(): void
    {
        $member = $this->makeMember();

        $this->consentQuery
            ->shouldReceive('hasConsent')
            ->once()
            ->with($member, 'marketing_email')
            ->andReturn(true);

        $this->assertTrue(
            $this->checker->canSend($member, CampaignPurpose::MARKETING, CampaignChannel::PUSH)
        );
    }

    // =========================================================================
    // Product updates
    // =========================================================================

    public function test_product_updates_checks_communication_preferences_consent(): void
    {
        $member = $this->makeMember();

        $this->consentQuery
            ->shouldReceive('hasConsent')
            ->once()
            ->with($member, 'communication_preferences')
            ->andReturn(true);

        $this->assertTrue(
            $this->checker->canSend($member, CampaignPurpose::PRODUCT_UPDATES, CampaignChannel::EMAIL)
        );
    }

    public function test_product_updates_blocked_without_communication_preferences_consent(): void
    {
        $member = $this->makeMember();

        $this->consentQuery
            ->shouldReceive('hasConsent')
            ->once()
            ->with($member, 'communication_preferences')
            ->andReturn(false);

        $this->assertFalse(
            $this->checker->canSend($member, CampaignPurpose::PRODUCT_UPDATES, CampaignChannel::EMAIL)
        );
    }

    // =========================================================================
    // Resilience
    // =========================================================================

    public function test_returns_false_when_consent_query_throws(): void
    {
        $member = $this->makeMember();

        $this->consentQuery
            ->shouldReceive('hasConsent')
            ->once()
            ->andThrow(new \RuntimeException('Consent type not found'));

        $this->assertFalse(
            $this->checker->canSend($member, CampaignPurpose::MARKETING, CampaignChannel::EMAIL)
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->consentQuery = Mockery::mock(ConsentQueryService::class);
        $this->checker = new CampaignConsentChecker($this->consentQuery);
    }
}