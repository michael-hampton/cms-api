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
use Mockery;
use PHPUnit\Framework\TestCase;

class ConsentCommandServiceTest extends TestCase
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

        $newConsent = Mockery::mock(MemberConsent::class)->makePartial();
        $newConsent->member_id = 1;
        $newConsent->consent_type_id = 1;

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->with('marketing_email')
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->with(1, $consentType->id)
            ->andReturn(null);

        $this->memberConsentRepository->shouldReceive('createNew')
            ->once()
            ->with([
                'member_id' => 1,
                'consent_type_id' => 1,
            ])
            ->andReturn($newConsent);

        $this->memberConsentRepository->shouldReceive('save')
            ->once()
            ->with($newConsent)
            ->andReturn(true);

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

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn($existingConsent);

        $this->memberConsentRepository->shouldReceive('save')
            ->once()
            ->with($existingConsent)
            ->andReturn(true);

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

        $newConsent = Mockery::mock(MemberConsent::class)->makePartial();

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn(null);

        $this->memberConsentRepository->shouldReceive('createNew')
            ->once()
            ->andReturn($newConsent);

        $this->memberConsentRepository->shouldReceive('save')
            ->once()
            ->andReturn(true);

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

        $this->consentTypeRepository->shouldReceive('findActiveByCode')
            ->once()
            ->andReturn($consentType);

        $this->memberConsentRepository->shouldReceive('findByMemberAndType')
            ->once()
            ->andReturn($existingConsent);

        $this->memberConsentRepository->shouldReceive('save')
            ->once()
            ->with($existingConsent)
            ->andReturn(true);

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

        $this->memberConsentRepository->shouldReceive('findExpired')
            ->once()
            ->andReturn(collect([$expiredConsent]));

        $this->memberConsentRepository->shouldReceive('save')
            ->once()
            ->with($expiredConsent)
            ->andReturn(true);

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

    public function testProcessWithdrawalRequestSpecificConsent()
    {
        $member = $this->createMockMember(1);
        $consentType = $this->createMockConsentType('marketing_email', false, null);

        $withdrawalRequest = Mockery::mock(ConsentWithdrawalRequest::class)->makePartial();
        $withdrawalRequest->member = $member;
        $withdrawalRequest->status = ConsentWithdrawalStatus::PENDING->value;
        $withdrawalRequest->type = ConsentWithdrawalType::SPECIFIC_CONSENT->value;
        $withdrawalRequest->consent_types = ['marketing_email'];
        $withdrawalRequest->shouldReceive('save')->once()->andReturn(true);

        $existingConsent = Mockery::mock(MemberConsent::class)->makePartial();
        $existingConsent->is_granted = true;

        $this->databaseMock->shouldReceive('transaction')
            ->twice() // Once for processWithdrawalRequest, once for revokeConsent
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

        $this->memberConsentRepository->shouldReceive('save')
            ->once()
            ->with($existingConsent)
            ->andReturn(true);

        $this->auditService->shouldReceive('log')->once();

        $result = $this->service->processWithdrawalRequest($withdrawalRequest, 1);

        $this->assertTrue($result);
        $this->assertEquals(ConsentWithdrawalStatus::COMPLETED->value, $withdrawalRequest->status);
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
        $withdrawalRequest->shouldReceive('save')->once()->andReturn(true);

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