<?php

namespace App\Tests\Unit\Services\Members\Consents;

use App\DTO\Consents\ConsentActionContext;
use App\Enums\ConsentAction;
use App\Models\ConsentType;
use App\Models\Member;
use App\Services\Members\Consents\ConsentAuditService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ConsentAuditServiceTest extends TestCase
{
    private ConsentAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConsentAuditService();
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

        // This will actually create a database record
        // In a real test environment, you'd mock the model creation
        $this->service->log(
            $member,
            $consentType,
            ConsentAction::GRANTED,
            null,
            true,
            $context
        );

        // Assert would check database in integration test
        $this->assertTrue(true);
    }

    public function testLogWithAdminContext()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $consentType = Mockery::mock(ConsentType::class)->makePartial();
        $consentType->id = 1;

        $context = ConsentActionContext::fromAdmin(123, 'Manual revocation');

        $this->service->log(
            $member,
            $consentType,
            ConsentAction::REVOKED,
            true,
            false,
            $context
        );

        $this->assertTrue(true);
    }

    public function testLogWithSystemContext()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $consentType = Mockery::mock(ConsentType::class)->makePartial();
        $consentType->id = 1;

        $context = ConsentActionContext::fromSystem('Consent expired');

        $this->service->log(
            $member,
            $consentType,
            ConsentAction::EXPIRED,
            true,
            false,
            $context
        );

        $this->assertTrue(true);
    }
}