<?php

namespace App\Tests\Unit\Services\Members\Consents;

use App\Models\ConsentType;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Repositories\Members\Consents\ConsentTypeRepository;
use App\Repositories\Members\Consents\MemberConsentRepository;
use App\Services\Members\Consents\ConsentQueryService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ConsentQueryServiceTest extends TestCase
{
    private $consentTypeRepository;
    private $memberConsentRepository;
    private ConsentQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consentTypeRepository = Mockery::mock(ConsentTypeRepository::class);
        $this->memberConsentRepository = Mockery::mock(MemberConsentRepository::class);

        $this->service = new ConsentQueryService(
            $this->consentTypeRepository,
            $this->memberConsentRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testHasConsentReturnsTrueForGranted()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false);

        $consent = Mockery::mock(MemberConsent::class)->makePartial();
        $consent->shouldReceive('isActive')->andReturn(true);

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->with('marketing_email')
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->with(1, 1)
            ->andReturn($consent);

        $result = $this->service->hasConsent($member, 'marketing_email');

        $this->assertTrue($result);
    }

    public function testHasConsentReturnsFalseForNotGranted()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false);

        $consent = Mockery::mock(MemberConsent::class)->makePartial();
        $consent->shouldReceive('isActive')->andReturn(false);

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn($consent);

        $result = $this->service->hasConsent($member, 'marketing_email');

        $this->assertFalse($result);
    }

    public function testHasConsentReturnsTrueForRequired()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('essential_cookies', true);

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $result = $this->service->hasConsent($member, 'essential_cookies');

        $this->assertTrue($result);
    }

    public function testHasConsentReturnsFalseForNotFound()
    {
        $member = $this->createMockMember(1);

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn(null);

        $result = $this->service->hasConsent($member, 'nonexistent');

        $this->assertFalse($result);
    }

    public function testHasConsentReturnsFalseWhenConsentNotFound()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false);

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn(null);

        $result = $this->service->hasConsent($member, 'marketing_email');

        $this->assertFalse($result);
    }

    // Around line 130-160 - Fix the assertion:

    public function testGetMemberConsents()
    {
        $member = $this->createMockMember(1);
        $consentType1 = $this->createMockConsentType('marketing_email', false);
        $consentType2 = $this->createMockConsentType('analytics', false);
        $consentType2->id = 2; // Different ID
        $consentType2->code = 'analytics';

        $consent1 = Mockery::mock(MemberConsent::class)->makePartial();
        $consent1->consent_type_id = 1;
        $consent1->granted_at = new \DateTime('2024-01-01');
        $consent1->expires_at = null;
        $consent1->channel = 'web';
        $consent1->shouldReceive('isActive')->andReturn(true);

        $this->consentTypeRepository->shouldReceive('findAllActive')
            ->once()
            ->andReturn(collect([$consentType1, $consentType2]));

        $this->memberConsentRepository->shouldReceive('findAllByMember')
            ->once()
            ->with(1)
            ->andReturn(collect([$consent1]));

        $result = $this->service->getMemberConsents($member);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        // Check structure - getMemberConsents returns flat arrays with MemberConsentSnapshot data
        $this->assertArrayHasKey('id', $result[0]['consent_type']);
        $this->assertArrayHasKey('code', $result[0]['consent_type']);
        $this->assertArrayHasKey('is_granted', $result[0]);

        // First consent (marketing_email) is granted
        $this->assertEquals('marketing_email', $result[0]['consent_type']['code']);
        $this->assertTrue($result[0]['is_granted']);

        // Second consent (analytics) is not granted (not in memberConsents)
        $this->assertEquals('analytics', $result[1]['consent_type']['code']);
        $this->assertFalse($result[1]['is_granted']);
    }

    public function testGetMemberConsentsWithNoConsents()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false);

        $this->consentTypeRepository->shouldReceive('findAllActive')
            ->once()
            ->andReturn(collect([$consentType]));

        $this->memberConsentRepository->shouldReceive('findAllByMember')
            ->once()
            ->andReturn(collect([]));

        $result = $this->service->getMemberConsents($member);

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['is_granted']);
    }

    public function testGetAuditTrail()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false);

        $this->consentTypeRepository->shouldReceive('getAuditLog')
            ->with(1, '')
            ->andReturn(collect([$consentType]));

        $trail = $this->service->getAuditTrail($member);

        $this->assertIsArray($trail);
    }

    public function testGetAuditTrailWithConsentCode()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false);

        // Mock the query builder
        $this->consentTypeRepository->shouldReceive('getAuditLog')
            ->with(1, 'marketing_email')
            ->andReturn(collect([$consentType]));

        $trail = $this->service->getAuditTrail($member, 'marketing_email');

        $this->assertIsArray($trail);
    }

    public function testGetConsentStatistics()
    {
        $consentType = $this->createMockConsentType('marketing_email', false);

        $query = Mockery::mock(\App\Framework\Database\QueryBuilder::class);
        $query->shouldReceive('count')->times(3)->andReturn(100, 75, 70);
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereHas')->andReturnSelf();

        $this->consentTypeRepository->shouldReceive('findAllActive')
            ->once()
            ->andReturn(collect([$consentType]));

        $this->memberConsentRepository->shouldReceive('queryByType')
            ->once()
            ->andReturn($query);

        $result = $this->service->getConsentStatistics();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('marketing_email', $result);
        $this->assertArrayHasKey('grant_rate', $result['marketing_email']);
        $this->assertEquals(75.0, $result['marketing_email']['grant_rate']);
    }

    public function testGetConsentStatisticsWithSiteFilter()
    {
        $consentType = $this->createMockConsentType('marketing_email', false);

        $query = Mockery::mock(\App\Framework\Database\QueryBuilder::class);
        $query->shouldReceive('count')->times(3)->andReturn(50, 40, 38);
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereHas')->andReturnSelf();

        $this->consentTypeRepository->shouldReceive('findAllActive')
            ->once()
            ->andReturn(collect([$consentType]));

        $this->memberConsentRepository->shouldReceive('queryByType')
            ->once()
            ->andReturn($query);

        $result = $this->service->getConsentStatistics(1);

        $this->assertArrayHasKey('marketing_email', $result);
    }

    public function testGetConsentStatisticsHandlesZeroTotal()
    {
        $consentType = $this->createMockConsentType('marketing_email', false);

        $query = Mockery::mock(\App\Framework\Database\QueryBuilder::class);
        $query->shouldReceive('count')->times(3)->andReturn(0, 0, 0);
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereHas')->andReturnSelf();

        $this->consentTypeRepository->shouldReceive('findAllActive')
            ->once()
            ->andReturn(collect([$consentType]));

        $this->memberConsentRepository->shouldReceive('queryByType')
            ->once()
            ->andReturn($query);

        $result = $this->service->getConsentStatistics();

        $this->assertEquals(0, $result['marketing_email']['grant_rate']);
    }

    private function createMockMember(int $id): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;
        return $member;
    }

    private function createMockConsentType(string $code, bool $required): ConsentType
    {
        $consentType = Mockery::mock(ConsentType::class)->makePartial();
        $consentType->id = 1;
        $consentType->code = $code;
        $consentType->name = ucfirst($code);
        $consentType->description = 'Test description';
        $consentType->category = 'marketing';
        $consentType->required = $required;
        $consentType->retention_days = null;
        $consentType->shouldReceive('isRequired')->andReturn($required);
        return $consentType;
    }
}