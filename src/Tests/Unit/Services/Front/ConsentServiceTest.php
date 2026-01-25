<?php

namespace App\Tests\Unit\Services\Front;

use App\Models\ConsentAuditLog;
use App\Models\ConsentType;
use App\Models\ConsentWithdrawalRequest;
use App\Models\MemberConsent;
use App\Services\Members\ConsentService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class ConsentServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private ConsentService $service;

    public function testGrantConsent()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType(['code' => 'marketing_email']);

        $consent = $this->service->grantConsent($member, 'marketing_email', 'web');

        $this->assertInstanceOf(MemberConsent::class, $consent);
        $this->assertTrue($consent->is_granted);
        $this->assertEquals('web', $consent->channel);
        $this->assertNotNull($consent->granted_at);
    }

    private function createConsentType(array $overrides = []): ConsentType
    {
        return ConsentType::create(array_merge([
            'code' => 'test_consent_' . uniqid(),
            'name' => 'Test Consent',
            'description' => 'Test description',
            'category' => 'marketing',
            'required' => false,
            'data_purposes' => [],
            'is_active' => true
        ], $overrides));
    }

    public function testGrantConsentWithExpiry()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType([
            'code' => 'analytics',
            'retention_days' => 365
        ]);

        $consent = $this->service->grantConsent($member, 'analytics', 'web');

        $this->assertNotNull($consent->expires_at);
        $this->assertTrue($consent->expires_at > now_datetime());
    }

    public function testRevokeConsent()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType(['code' => 'marketing_email', 'required' => false]);

        $this->service->grantConsent($member, 'marketing_email', 'web');
        $result = $this->service->revokeConsent($member, 'marketing_email', 'web');

        $this->assertTrue($result);

        $consent = MemberConsent::where('member_id', $member->id)
            ->where('consent_type_id', $consentType->id)
            ->first();

        $this->assertFalse($consent->is_granted);
        $this->assertNotNull($consent->revoked_at);
    }

    public function testCannotRevokeRequiredConsent()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot revoke required consent');

        $member = $this->createMember();
        $consentType = $this->createConsentType([
            'code' => 'essential_cookies',
            'required' => true
        ]);

        $this->service->revokeConsent($member, 'essential_cookies', 'web');
    }

    public function testUpdateConsents()
    {
        $member = $this->createMember();
        $this->createConsentType(['code' => 'marketing_email']);
        $this->createConsentType(['code' => 'analytics']);

        $consents = [
            'marketing_email' => true,
            'analytics' => false
        ];

        $results = $this->service->updateConsents($member, $consents, 'web');

        $this->assertArrayHasKey('marketing_email', $results);
        $this->assertArrayHasKey('analytics', $results);
    }

    public function testHasConsent()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType(['code' => 'marketing_email']);

        $this->service->grantConsent($member, 'marketing_email', 'web');

        $this->assertTrue($this->service->hasConsent($member, 'marketing_email'));
    }

    public function testHasConsentReturnsTrueForRequiredConsents()
    {
        $member = $this->createMember();
        $this->createConsentType([
            'code' => 'essential_cookies',
            'required' => true
        ]);

        $this->assertTrue($this->service->hasConsent($member, 'essential_cookies'));
    }

    public function testGetMemberConsents()
    {
        $member = $this->createMember();
        $this->createConsentType(['code' => 'marketing_email']);
        $this->createConsentType(['code' => 'analytics']);

        $this->service->grantConsent($member, 'marketing_email', 'web');

        $consents = $this->service->getMemberConsents($member);

        $this->assertIsArray($consents);
        $this->assertCount(2, $consents);
    }

    public function testProcessExpiredConsents()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType(['code' => 'analytics']);

        $consent = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web',
            'granted_at' => now_datetime()->modify('-2 days')->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('-1 day')->format('Y-m-d H:i:s')
        ]);

        $count = $this->service->processExpiredConsents();

        $this->assertEquals(1, $count);

        $fresh = MemberConsent::find($consent->id);
        $this->assertFalse($fresh->is_granted);
        $this->assertNotNull($fresh->revoked_at);
    }

    public function testCreateWithdrawalRequest()
    {
        $member = $this->createMember();

        $request = $this->service->createWithdrawalRequest(
            $member,
            'all_marketing',
            []
        );

        $this->assertInstanceOf(ConsentWithdrawalRequest::class, $request);
        $this->assertEquals('pending', $request->status);
        $this->assertEquals('all_marketing', $request->type);
    }

    public function testProcessWithdrawalRequestSpecificConsent()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType(['code' => 'marketing_email', 'required' => false]);

        $this->service->grantConsent($member, 'marketing_email', 'web');

        $withdrawalRequest = $this->service->createWithdrawalRequest(
            $member,
            'specific_consent',
            ['marketing_email']
        );

        $result = $this->service->processWithdrawalRequest($withdrawalRequest, 1);

        $this->assertTrue($result);
        $this->assertEquals('completed', $withdrawalRequest->status);

        $consent = MemberConsent::where('member_id', $member->id)
            ->where('consent_type_id', $consentType->id)
            ->first();

        $this->assertFalse($consent->is_granted);
    }

    public function testProcessWithdrawalRequestAllMarketing()
    {
        $member = $this->createMember();
        $this->createConsentType(['code' => 'marketing_email', 'category' => 'marketing', 'required' => false]);
        $this->createConsentType(['code' => 'targeted_ads', 'category' => 'marketing', 'required' => false]);

        $this->service->grantConsent($member, 'marketing_email', 'web');
        $this->service->grantConsent($member, 'targeted_ads', 'web');

        $withdrawalRequest = $this->service->createWithdrawalRequest(
            $member,
            'all_marketing',
            null
        );

        $result = $this->service->processWithdrawalRequest($withdrawalRequest, 1);

        $this->assertTrue($result);
        $this->assertFalse($this->service->hasConsent($member, 'marketing_email'));
        $this->assertFalse($this->service->hasConsent($member, 'targeted_ads'));
    }

    public function testGetConsentStatistics()
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $consentType = $this->createConsentType(['code' => 'marketing_email']);

        $this->service->grantConsent($member1, 'marketing_email', 'web');
        $this->service->grantConsent($member2, 'marketing_email', 'web');

        $stats = $this->service->getConsentStatistics();

        $this->assertArrayHasKey('marketing_email', $stats);
        $this->assertEquals(2, $stats['marketing_email']['granted']);
    }

    public function testAuditTrailCreated()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType(['code' => 'marketing_email']);

        $this->service->grantConsent($member, 'marketing_email', 'web');

        $auditLogs = ConsentAuditLog::where('member_id', $member->id)
            ->where('consent_type_id', $consentType->id)
            ->get();

        $this->assertCount(1, $auditLogs);
        $this->assertEquals('granted', $auditLogs->first()->action);
    }

    public function testGetAuditTrail()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType(['code' => 'marketing_email', 'required' => false]);

        $this->service->grantConsent($member, 'marketing_email', 'web');
        $this->service->revokeConsent($member, 'marketing_email', 'web');

        $trail = $this->service->getAuditTrail($member);

        $this->assertIsArray($trail);
        $this->assertCount(2, $trail);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->service = new ConsentService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}