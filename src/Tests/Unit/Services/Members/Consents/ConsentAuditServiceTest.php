<?php

namespace App\Tests\Unit\Services\Members\Consents;

use App\DTO\Consents\ConsentActionContext;
use App\Enums\ConsentAction;
use App\Models\ConsentAuditLog;
use App\Models\ConsentType;
use App\Models\Member;
use App\Repositories\Members\Consents\ConsentAuditLogRepository;
use App\Services\Members\Consents\ConsentAuditService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ConsentAuditServiceTest extends TestCase
{
    private $auditLogRepository;
    private ConsentAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditLogRepository = Mockery::mock(ConsentAuditLogRepository::class);
        $this->service = new ConsentAuditService($this->auditLogRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLogCreatesAuditEntry()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $consentType = Mockery::mock(ConsentType::class)->makePartial();
        $consentType->id = 1;

        $context = new ConsentActionContext(
            source: 'web',
            reason: null,
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            adminUserId: null,
            siteId: 1
        );

        $mockLog = Mockery::mock(ConsentAuditLog::class);

        $this->auditLogRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($member, $consentType, $context) {
                return $data['member_id'] === $member->id
                    && $data['consent_type_id'] === $consentType->id
                    && $data['action'] === ConsentAction::GRANTED->value
                    && $data['previous_state'] === null
                    && $data['new_state'] === true
                    && $data['source'] === 'web'
                    && $data['ip_address'] === '127.0.0.1';
            }))
            ->andReturn($mockLog);

        $result = $this->service->log(
            $member,
            $consentType,
            ConsentAction::GRANTED,
            null,
            true,
            $context
        );
        $this->assertInstanceOf(ConsentAuditLog::class, $result);
    }

    public function testLogWithAdminContext()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $consentType = Mockery::mock(ConsentType::class)->makePartial();
        $consentType->id = 1;

        $context = ConsentActionContext::fromAdmin(123, 'Manual revocation');

        $mockLog = Mockery::mock(ConsentAuditLog::class);

        $this->auditLogRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['action'] === ConsentAction::REVOKED->value
                    && $data['admin_user_id'] === 123
                    && $data['reason'] === 'Manual revocation'
                    && $data['source'] === 'admin';
            }))
            ->andReturn($mockLog);

        $result = $this->service->log(
            $member,
            $consentType,
            ConsentAction::REVOKED,
            true,
            false,
            $context
        );

        $this->assertInstanceOf(ConsentAuditLog::class, $result);
    }

    public function testLogWithSystemContext()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $consentType = Mockery::mock(ConsentType::class)->makePartial();
        $consentType->id = 1;

        $context = ConsentActionContext::fromSystem('Consent expired');

        $mockLog = Mockery::mock(ConsentAuditLog::class);

        $this->auditLogRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['action'] === ConsentAction::EXPIRED->value
                    && $data['source'] === 'system'
                    && $data['reason'] === 'Consent expired';
            }))
            ->andReturn($mockLog);

        $result = $this->service->log(
            $member,
            $consentType,
            ConsentAction::EXPIRED,
            true,
            false,
            $context
        );

        $this->assertInstanceOf(ConsentAuditLog::class, $result);
    }
}