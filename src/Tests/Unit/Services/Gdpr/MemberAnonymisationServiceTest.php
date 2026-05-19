<?php

namespace App\Tests\Unit\Services\Gdpr;

use App\DTO\Merchant\AnalyticsSnapshot;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Gdpr\MemberAnonymisationService;
use App\Services\Gdpr\GdprAuditLogger;
use App\Framework\Database\Database;
use App\Services\Gdpr\MemberDataCleaner;
use App\Services\Gdpr\MemberDataCleanerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Mockery;

class MemberAnonymisationServiceTest extends TestCase
{
    private MemberRepository $memberRepository;
    private Database $db;
    private GdprAuditLogger $auditLogger;
    private MemberDataCleanerInterface $dataCleaner;
    private MemberanonymisationService $anonymisationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->auditLogger = Mockery::mock(GdprAuditLogger::class);
        $this->db = Mockery::mock(Database::class);
        $this->dataCleaner = Mockery::mock(MemberDataCleaner::class);

        $this->anonymisationService = new MemberAnonymisationService(
            $this->db,
            $this->auditLogger,
            $this->memberRepository,
            $this->dataCleaner
        );
    }
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_throws_if_member_not_found()
    {
        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $this->anonymisationService->anonymise(1, 99);
    }

    public function test_it_throws_if_already_anonymised()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->is_forgotten = true;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->andReturn($member);

        $this->expectException(RuntimeException::class);

        $this->anonymisationService->anonymise(1, 99);
    }

    public function test_it_runs_full_anonymisation_flow(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;
        $member->is_forgotten = false;

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($member);

        // transaction wrapper
        $this->db
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        // audit logging
        $this->auditLogger
            ->shouldReceive('logAdminAction')
            ->twice();

        // core profile update expectation
        $this->memberRepository
            ->shouldReceive('update')
            ->once()
            ->with(10, Mockery::on(function ($m) {
                return $m['is_forgotten'] === true
                    && $m['is_active'] === false;
            }));

        // cleaner calls (this replaces ALL static ORM calls)
        $this->dataCleaner
            ->shouldReceive('deleteAddresses')
            ->once()
            ->with(10);

        $this->dataCleaner
            ->shouldReceive('deleteNotes')
            ->once()
            ->with(10);

        $this->dataCleaner
            ->shouldReceive('deleteNotifications')
            ->once()
            ->with(10);

        $this->dataCleaner
            ->shouldReceive('revokeConsents')
            ->once()
            ->with(10);

        $this->dataCleaner
            ->shouldReceive('disableSubscriptions')
            ->once()
            ->with(10);

        $this->anonymisationService->anonymise(10, 1);

        $this->assertTrue(true);
    }

    public function test_it_bubbles_up_transaction_exceptions(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 20;
        $member->is_forgotten = false;

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($member);

        $this->db
            ->shouldReceive('transaction')
            ->once()
            ->andThrow(new RuntimeException('DB failure'));

        $this->expectException(RuntimeException::class);

        $this->auditLogger->shouldReceive('logAdminAction')->once();

        $this->anonymisationService->anonymise(20, 1);
    }

    public function test_it_logs_before_and_after_execution(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 30;
        $member->is_forgotten = false;

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($member);

        $this->db
            ->shouldReceive('transaction')
            ->andReturnUsing(fn ($cb) => $cb());

        $this->auditLogger
            ->shouldReceive('logAdminAction')
            ->once()
            ->with('rtbf_requested', 30, 1);

        $this->auditLogger
            ->shouldReceive('logAdminAction')
            ->once()
            ->with(
                'rtbf_executed',
                30,
                1,
                Mockery::on(fn ($meta) => isset($meta['anonymised_at']))
            );

        $this->memberRepository
            ->shouldReceive('update')
            ->once();

        $this->dataCleaner
            ->shouldReceive('deleteAddresses')->once();

        $this->dataCleaner
            ->shouldReceive('deleteNotes')->once();

        $this->dataCleaner
            ->shouldReceive('deleteNotifications')->once();

        $this->dataCleaner
            ->shouldReceive('revokeConsents')->once();

        $this->dataCleaner
            ->shouldReceive('disableSubscriptions')->once();

        $this->anonymisationService->anonymise(30, 1);

        $this->assertTrue(true);
    }

    public function test_is_idempotent_when_flag_exists()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 5;
        $member->is_forgotten = true;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->andReturn($member);

        $this->expectException(RuntimeException::class);

        $this->anonymisationService->anonymise(5, 1);
    }
}