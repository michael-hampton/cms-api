<?php

namespace App\Tests\Unit\Services\Members\Consents;

use App\DTO\Consents\ConsentActionContext;
use App\Enums\ConsentAction;
use App\Enums\ConsentWithdrawalStatus;
use App\Enums\ConsentWithdrawalType;
use App\Exceptions\Consents\ConsentTypeNotFoundException;
use App\Exceptions\Consents\ConsentWithdrawalInvalidStateException;
use App\Exceptions\Consents\RequiredConsentCannotBeRevokedException;
use App\Framework\Database\Database;
use App\Models\ConsentType;
use App\Models\ConsentWithdrawalRequest;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Repositories\Members\Consents\ConsentTypeRepository;
use App\Repositories\Members\Consents\MemberConsentRepository;
use App\Services\Members\Consents\ConsentAuditService;
use App\Services\Members\Consents\ConsentCommandService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class ConsentCommandServiceTest extends FunctionalTestCase
{
    private $databaseMock;
    private $consentTypeRepository;
    private $memberConsentRepository;
    private $auditService;
    private ConsentCommandService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseMock = Mockery::mock(Database::class);
        $this->consentTypeRepository = Mockery::mock(ConsentTypeRepository::class);
        $this->memberConsentRepository = Mockery::mock(MemberConsentRepository::class);
        $this->auditService = Mockery::mock(ConsentAuditService::class);

        $this->service = new ConsentCommandService(
            $this->databaseMock,
            $this->consentTypeRepository,
            $this->memberConsentRepository,
            $this->auditService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGrantConsentCreatesNewConsent()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false, null);
        $context = new ConsentActionContext('web', ipAddress: '127.0.0.1');

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->with('marketing_email')
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->with(1, $consentType->id)
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->auditService->shouldReceive('log')
            ->once()
            ->with(
                $member,
                $consentType,
                ConsentAction::GRANTED,
                null,
                true,
                $context
            );

        $result = $this->service->grantConsent($member, 'marketing_email', $context);

        $this->assertInstanceOf(MemberConsent::class, $result);
        $this->assertTrue($result->is_granted);
        $this->assertEquals('web', $result->channel);
    }

    public function testGrantConsentUpdatesExistingConsent()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false, null);
        $context = new ConsentActionContext('web');

        $existingConsent = Mockery::mock(MemberConsent::class)->makePartial();
        $existingConsent->is_granted = false;
        $existingConsent->shouldReceive('save')->once()->andReturn(true);

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn($existingConsent);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->auditService->shouldReceive('log')->once();

        $result = $this->service->grantConsent($member, 'marketing_email', $context);

        $this->assertTrue($result->is_granted);
    }

    public function testGrantConsentWithRetentionPeriod()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('analytics', false, 365);
        $context = new ConsentActionContext('web');

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->auditService->shouldReceive('log')->once();

        $result = $this->service->grantConsent($member, 'analytics', $context);

        $this->assertNotNull($result->expires_at);
    }

    public function testGrantConsentThrowsExceptionForInvalidCode()
    {
        $member = $this->createMockMember(1);
        $context = new ConsentActionContext('web');

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->with('invalid_code')
            ->andThrow(new ConsentTypeNotFoundException('invalid_code'));

        $this->expectException(ConsentTypeNotFoundException::class);

        $this->service->grantConsent($member, 'invalid_code', $context);
    }

    public function testRevokeConsentSuccess()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false, null);
        $context = new ConsentActionContext('web');

        $existingConsent = Mockery::mock(MemberConsent::class)->makePartial();
        $existingConsent->is_granted = true;
        $existingConsent->shouldReceive('save')->once()->andReturn(true);

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn($existingConsent);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->auditService->shouldReceive('log')
            ->once()
            ->with(
                $member,
                $consentType,
                ConsentAction::REVOKED,
                true,
                false,
                $context
            );

        $result = $this->service->revokeConsent($member, 'marketing_email', $context);

        $this->assertTrue($result);
        $this->assertFalse($existingConsent->is_granted);
        $this->assertNotNull($existingConsent->revoked_at);
    }

    public function testRevokeConsentThrowsExceptionForRequired()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('essential_cookies', true, null);
        $context = new ConsentActionContext('web');

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->expectException(RequiredConsentCannotBeRevokedException::class);

        $this->service->revokeConsent($member, 'essential_cookies', $context);
    }

    public function testRevokeConsentReturnsFalseWhenNotFound()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false, null);
        $context = new ConsentActionContext('web');

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->revokeConsent($member, 'marketing_email', $context);

        $this->assertFalse($result);
    }

    public function testProcessExpiredConsents()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('analytics', false, 365);

        $expiredConsent = Mockery::mock(MemberConsent::class)->makePartial();
        $expiredConsent->member = $member;
        $expiredConsent->consentType = $consentType;
        $expiredConsent->is_granted = true;
        $expiredConsent->shouldReceive('save')->once()->andReturn(true);

        $this->memberConsentRepository->shouldReceive('findExpired')
            ->once()
            ->andReturn(collect([$expiredConsent]));

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->auditService->shouldReceive('log')
            ->once()
            ->with(
                $member,
                $consentType,
                ConsentAction::EXPIRED,
                true,
                false,
                Mockery::type(ConsentActionContext::class)
            );

        $count = $this->service->processExpiredConsents();

        $this->assertEquals(1, $count);
        $this->assertFalse($expiredConsent->is_granted);
    }

    public function testProcessExpiredConsentsWithMultiple()
    {
        $member1 = $this->createMockMember(1);
        $member2 = $this->createMockMember(2);
        $consentType = $this->createMockConsentType('analytics', false, 365);

        $expired1 = Mockery::mock(MemberConsent::class)->makePartial();
        $expired1->member = $member1;
        $expired1->consentType = $consentType;
        $expired1->shouldReceive('save')->once()->andReturn(true);

        $expired2 = Mockery::mock(MemberConsent::class)->makePartial();
        $expired2->member = $member2;
        $expired2->consentType = $consentType;
        $expired2->shouldReceive('save')->once()->andReturn(true);

        $this->memberConsentRepository->shouldReceive('findExpired')
            ->once()
            ->andReturn(collect([$expired1, $expired2]));

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->auditService->shouldReceive('log')->twice();

        $count = $this->service->processExpiredConsents();

        $this->assertEquals(2, $count);
    }

    public function testProcessWithdrawalRequestSpecificConsent()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false, null);

        $withdrawalRequest = Mockery::mock(ConsentWithdrawalRequest::class)->makePartial();
        $withdrawalRequest->member = $member;
        $withdrawalRequest->status = ConsentWithdrawalStatus::PENDING->value;
        $withdrawalRequest->type = ConsentWithdrawalType::SPECIFIC_CONSENT->value;
        $withdrawalRequest->consent_types = ['marketing_email'];
        $withdrawalRequest->shouldReceive('save')->twice()->andReturn(true);

        $existingConsent = Mockery::mock(MemberConsent::class)->makePartial();
        $existingConsent->is_granted = true;
        $existingConsent->shouldReceive('save')->once()->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->with('marketing_email')
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn($existingConsent);

        $this->auditService->shouldReceive('log')->once();

        $result = $this->service->processWithdrawalRequest($withdrawalRequest, 1);

        $this->assertTrue($result);
        $this->assertEquals(ConsentWithdrawalStatus::COMPLETED->value, $withdrawalRequest->status);
    }

    public function testProcessWithdrawalRequestAllMarketing()
    {
        $member = $this->createMockMember(1);
        $consentType1 = $this->createMockConsentType('marketing_email', false, null);
        $consentType2 = $this->createMockConsentType('targeted_ads', false, null);

        $withdrawalRequest = Mockery::mock(ConsentWithdrawalRequest::class)->makePartial();
        $withdrawalRequest->member = $member;
        $withdrawalRequest->status = ConsentWithdrawalStatus::PENDING->value;
        $withdrawalRequest->type = ConsentWithdrawalType::ALL_MARKETING->value;
        $withdrawalRequest->shouldReceive('save')->twice()->andReturn(true);

        $consent1 = Mockery::mock(MemberConsent::class)->makePartial();
        $consent1->shouldReceive('save')->once()->andReturn(true);

        $consent2 = Mockery::mock(MemberConsent::class)->makePartial();
        $consent2->shouldReceive('save')->once()->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->times(3)
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->consentTypeRepository->shouldReceive('findActiveByCategory')
            ->once()
            ->with('marketing')
            ->andReturn(collect([$consentType1, $consentType2]));

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->twice()
            ->andReturnValues([$consentType1, $consentType2]);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->twice()
            ->andReturnValues([$consent1, $consent2]);

        $this->auditService->shouldReceive('log')->twice();

        $result = $this->service->processWithdrawalRequest($withdrawalRequest, 1);

        $this->assertTrue($result);
    }

    public function testProcessWithdrawalRequestThrowsExceptionForInvalidState()
    {
        $withdrawalRequest = Mockery::mock(ConsentWithdrawalRequest::class)->makePartial();
        $withdrawalRequest->status = ConsentWithdrawalStatus::COMPLETED->value;

        $this->expectException(ConsentWithdrawalInvalidStateException::class);

        $this->service->processWithdrawalRequest($withdrawalRequest, 1);
    }

    public function testProcessWithdrawalRequestHandlesExceptions()
    {
        $member = $this->createMockMember(1);

        $withdrawalRequest = Mockery::mock(ConsentWithdrawalRequest::class)->makePartial();
        $withdrawalRequest->member = $member;
        $withdrawalRequest->status = ConsentWithdrawalStatus::PENDING->value;
        $withdrawalRequest->type = ConsentWithdrawalType::SPECIFIC_CONSENT->value;
        $withdrawalRequest->consent_types = ['invalid'];
        $withdrawalRequest->shouldReceive('save')->twice()->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andThrow(new ConsentTypeNotFoundException('invalid'));

        $this->expectException(ConsentTypeNotFoundException::class);

        $this->service->processWithdrawalRequest($withdrawalRequest, 1);

        $this->assertEquals(ConsentWithdrawalStatus::CANCELLED->value, $withdrawalRequest->status);
    }

    private function createMockMember(int $id): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;
        return $member;
    }

    private function createMockConsentType(
        string $code,
        bool   $required,
        ?int   $retentionDays
    ): ConsentType
    {
        $consentType = Mockery::mock(ConsentType::class)->makePartial();
        $consentType->id = 1;
        $consentType->code = $code;
        $consentType->required = $required;
        $consentType->retention_days = $retentionDays;
        $consentType->shouldReceive('isRequired')->andReturn($required);
        return $consentType;
    }
}