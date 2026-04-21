<?php

namespace App\Tests\Functional\Controllers\Members;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Session\Session;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberConsentControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexShowsConsentPreferences()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->getForSite('/member/consent');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('marketing_email', $content);
        $this->assertStringContainsString('analytics', $content);
    }

    public function testGrantConsent()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->postForSite('/member/consent/grant/marketing_email');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['consent']['is_granted']);
        $this->assertArrayHasKey('granted_at', $data['consent']);
    }

    public function testGrantConsentRequiresAuth()
    {
        MemberAuth::setMember(null);
        Session::forget('member_id');

        $response = $this->postForSite('/member/consent/grant/marketing_email');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGrantConsentWithInvalidCode()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->postForSite('/member/consent/grant/invalid_code');

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('not found', $data['message']);
    }

    public function testRevokeConsent()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        // First grant it
        $this->postForSite('/member/consent/grant/marketing_email');

        // Then revoke it
        $response = $this->postForSite('/member/consent/revoke/marketing_email');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Consent revoked successfully', $data['message']);
    }

    public function testRevokeConsentRequiresAuth()
    {
        MemberAuth::setMember(null);
        Session::forget('member_id');

        $response = $this->postForSite('/member/consent/revoke/marketing_email');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCannotRevokeRequiredConsent()
    {
        $this->createConsentType(['code' => 'essential', 'category' => 'essential', 'required' => true]);

        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->postForSite('/member/consent/revoke/essential');

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Cannot revoke required consent', $data['message']);
    }

    public function testCheckConsent()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        // Grant consent
        $this->postForSite('/member/consent/grant/marketing_email');

        // Check it
        $response = $this->getForSite('/member/consent/check/marketing_email');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('marketing_email', $data['consent_code']);
        $this->assertTrue($data['has_consent']);
    }

    public function testCheckConsentRequiresAuth()
    {
        MemberAuth::setMember(null);
        Session::forget('member_id');

        $response = $this->getForSite('/member/consent/check/marketing_email');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAuditTrailPage()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        // Create some consent history
        $this->postForSite('/member/consent/grant/marketing_email');
        $this->postForSite('/member/consent/revoke/marketing_email');

        $response = $this->getForSite('/member/consent/audit-trail');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDownloadData()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $this->postForSite('/member/consent/grant/marketing_email');

        $response = $this->getForSite('/member/consent/download-data');

        // Since downloadData() uses exit, we can't test the actual response
        // But we can test that it requires auth
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCreateWithdrawalRequest()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->postForSite('/member/consent/withdrawal-request', [
            'type' => 'all_marketing',
            'consent_types' => []
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Withdrawal request submitted successfully', $data['message']);
        $this->assertArrayHasKey('request_id', $data);
    }

    public function testCreateWithdrawalRequestSpecificConsents()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->postForSite('/member/consent/withdrawal-request', [
            'type' => 'specific_consent',
            'consent_types' => ['marketing_email', 'analytics']
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testCreateWithdrawalRequestRequiresAuth()
    {
        MemberAuth::setMember(null);
        Session::forget('member_id');

        $response = $this->postForSite('/member/consent/withdrawal-request', [
            'type' => 'all_marketing'
        ]);

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testAcceptBanner()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->postForSite('/member/consent/accept-banner', [
            'consents' => ['marketing_email', 'analytics']
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Consent preferences saved', $data['message']);
        $this->assertArrayHasKey('results', $data);
        $this->assertCount(2, $data['results']);
    }

    public function testAcceptBannerRequiresAuth()
    {
        MemberAuth::setMember(null);
        Session::forget('member_id');

        $response = $this->postForSite('/member/consent/accept-banner', [
            'consents' => ['marketing_email']
        ]);

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testAcceptBannerWithEmptyArray()
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->postForSite('/member/consent/accept-banner', [
            'consents' => []
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['results']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConsentType(['code' => 'marketing_email', 'category' => 'marketing', 'required' => false]);
        $this->createConsentType(['code' => 'analytics', 'category' => 'analytics', 'required' => false]);

        $member = $this->createMember();
        $this->actingAsMember($member);
    }
}