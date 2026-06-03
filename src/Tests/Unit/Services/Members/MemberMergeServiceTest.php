<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Members;

use App\Exceptions\Members\MergeConflictException;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Repositories\Members\MemberMergeRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\Members\MemberMergeService;
use Mockery;
use PHPUnit\Framework\TestCase;

final class MemberMergeServiceTest extends TestCase
{
    private MemberRepository $memberRepository;
    private MemberMergeRepository $memberMergeRepository;
    private Database $database;
    private MemberMergeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->memberMergeRepository = Mockery::mock(MemberMergeRepository::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new MemberMergeService(
            $this->memberRepository,
            $this->memberMergeRepository,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_merge_throws_exception_when_primary_and_secondary_are_same(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Primary and secondary member cannot be the same account.');

        $this->service->merge(1, 1, 99);
    }

    public function test_merge_throws_exception_when_primary_member_not_found(): void
    {
        $secondary = $this->makeMember(2, 'secondary@example.com');

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn($secondary);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Primary member #1 not found.');

        $this->service->merge(1, 2, 99);
    }

    public function test_merge_throws_exception_when_secondary_member_not_found(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com');

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($primary);

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Secondary member #2 not found.');

        $this->service->merge(1, 2, 99);
    }

    function test_merge_reassigns_data_marks_secondary_as_merged_and_records_audit_inside_transaction(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com');
        $secondary = $this->makeMember(2, 'secondary@example.com');

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($primary);

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn($secondary);

        $this->memberRepository
            ->shouldReceive('countActiveSubscriptions')
            ->once()
            ->with(1)
            ->andReturn(0);

        $this->memberRepository
            ->shouldReceive('countActiveSubscriptions')
            ->once()
            ->with(2)
            ->andReturn(0);

        $this->memberRepository
            ->shouldReceive('hasPendingPayments')
            ->once()
            ->with(2)
            ->andReturn(false);

        $this->memberRepository
            ->shouldReceive('reassignOrders')
            ->once()
            ->with(2, 1);

        $this->memberRepository
            ->shouldReceive('reassignSubscriptions')
            ->once()
            ->with(2, 1);

        $this->memberRepository
            ->shouldReceive('reassignPayments')
            ->once()
            ->with(2, 1);

        $this->memberRepository
            ->shouldReceive('reassignNotes')
            ->once()
            ->with(2, 1);

        $this->memberRepository
            ->shouldReceive('mergeAddresses')
            ->once()
            ->with(2, 1);

        $this->memberRepository
            ->shouldReceive('markAsMerged')
            ->once()
            ->with(
                2,
                1,
                99,
                Mockery::type('string')
            );

        $this->memberMergeRepository
            ->shouldReceive('recordMerge')
            ->once()
            ->with(
                1,
                2,
                99,
                Mockery::type('string'),
                'Duplicate account',
                [
                    'primary_email' => 'primary@example.com',
                    'secondary_email' => 'secondary@example.com',
                ]
            );

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->service->merge(1, 2, 99, [
            'reason' => 'Duplicate account',
        ]);

        $this->assertTrue(true);
    }

    public function test_detect_conflicts_detects_conflicting_stripe_customer_ids(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com', 'cus_primary');
        $secondary = $this->makeMember(2, 'secondary@example.com', 'cus_secondary');

        $this->memberRepository
            ->shouldReceive('countActiveSubscriptions')
            ->once()
            ->with(1)
            ->andReturn(0);

        $this->memberRepository
            ->shouldReceive('countActiveSubscriptions')
            ->once()
            ->with(2)
            ->andReturn(0);

        $this->memberRepository
            ->shouldReceive('hasPendingPayments')
            ->once()
            ->with(2)
            ->andReturn(false);

        $conflicts = $this->service->detectConflicts($primary, $secondary);

        $this->assertContains(
            'conflicting_stripe_customers',
            array_column($conflicts, 'code')
        );
    }

    public function test_assert_no_conflicts_throws_when_conflicts_exist(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com', 'cus_primary');
        $secondary = $this->makeMember(2, 'secondary@example.com', 'cus_secondary');

        $this->memberRepository
            ->shouldReceive('countActiveSubscriptions')
            ->once()
            ->with(1)
            ->andReturn(0);

        $this->memberRepository
            ->shouldReceive('countActiveSubscriptions')
            ->once()
            ->with(2)
            ->andReturn(0);

        $this->memberRepository
            ->shouldReceive('hasPendingPayments')
            ->once()
            ->with(2)
            ->andReturn(false);

        $this->expectException(MergeConflictException::class);

        $this->service->assertNoConflicts($primary, $secondary);
    }

    public function test_merge_throws_when_active_subscription_conflict_exists(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com');
        $secondary = $this->makeMember(2, 'secondary@example.com');

        $this->memberRepository->shouldReceive('find')->once()->with(1)->andReturn($primary);
        $this->memberRepository->shouldReceive('find')->once()->with(2)->andReturn($secondary);

        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(1)->andReturn(1);
        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(2)->andReturn(1);
        $this->memberRepository->shouldReceive('hasPendingPayments')->once()->with(2)->andReturn(false);

        $this->expectException(MergeConflictException::class);

        $this->service->merge(1, 2, 99);
    }

    public function test_merge_throws_when_pending_payments_exist(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com');
        $secondary = $this->makeMember(2, 'secondary@example.com');

        $this->memberRepository->shouldReceive('find')->once()->with(1)->andReturn($primary);
        $this->memberRepository->shouldReceive('find')->once()->with(2)->andReturn($secondary);

        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(1)->andReturn(0);
        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(2)->andReturn(0);
        $this->memberRepository->shouldReceive('hasPendingPayments')->once()->with(2)->andReturn(true);

        $this->expectException(MergeConflictException::class);

        $this->service->merge(1, 2, 99);
    }

    public function test_detect_conflicts_detects_both_active_subscriptions(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com');
        $secondary = $this->makeMember(2, 'secondary@example.com');

        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(1)->andReturn(1);
        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(2)->andReturn(1);
        $this->memberRepository->shouldReceive('hasPendingPayments')->once()->with(2)->andReturn(false);

        $conflicts = $this->service->detectConflicts($primary, $secondary);

        $this->assertContains('active_subscriptions', array_column($conflicts, 'code'));
    }

    public function test_detect_conflicts_detects_pending_payments(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com');
        $secondary = $this->makeMember(2, 'secondary@example.com');

        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(1)->andReturn(0);
        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(2)->andReturn(0);
        $this->memberRepository->shouldReceive('hasPendingPayments')->once()->with(2)->andReturn(true);

        $conflicts = $this->service->detectConflicts($primary, $secondary);

        $this->assertContains('pending_payments', array_column($conflicts, 'code'));
    }

    public function test_detect_conflicts_detects_conflicting_verified_emails(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com', null, true);
        $secondary = $this->makeMember(2, 'secondary@example.com', null, true);

        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(1)->andReturn(0);
        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(2)->andReturn(0);
        $this->memberRepository->shouldReceive('hasPendingPayments')->once()->with(2)->andReturn(false);

        $conflicts = $this->service->detectConflicts($primary, $secondary);

        $this->assertContains('conflicting_verified_emails', array_column($conflicts, 'code'));
    }

    public function test_detect_conflicts_returns_empty_when_no_conflicts_exist(): void
    {
        $primary = $this->makeMember(1, 'same@example.com', null, false);
        $secondary = $this->makeMember(2, 'same@example.com', null, false);

        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(1)->andReturn(0);
        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(2)->andReturn(0);
        $this->memberRepository->shouldReceive('hasPendingPayments')->once()->with(2)->andReturn(false);

        $conflicts = $this->service->detectConflicts($primary, $secondary);

        $this->assertSame([], $conflicts);
    }

    public function test_merge_records_null_reason_when_no_reason_given(): void
    {
        $primary = $this->makeMember(1, 'primary@example.com');
        $secondary = $this->makeMember(2, 'secondary@example.com');

        $this->memberRepository->shouldReceive('find')->once()->with(1)->andReturn($primary);
        $this->memberRepository->shouldReceive('find')->once()->with(2)->andReturn($secondary);

        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(1)->andReturn(0);
        $this->memberRepository->shouldReceive('countActiveSubscriptions')->once()->with(2)->andReturn(0);
        $this->memberRepository->shouldReceive('hasPendingPayments')->once()->with(2)->andReturn(false);

        $this->memberRepository->shouldReceive('reassignOrders')->once()->with(2, 1);
        $this->memberRepository->shouldReceive('reassignSubscriptions')->once()->with(2, 1);
        $this->memberRepository->shouldReceive('reassignPayments')->once()->with(2, 1);
        $this->memberRepository->shouldReceive('reassignNotes')->once()->with(2, 1);
        $this->memberRepository->shouldReceive('mergeAddresses')->once()->with(2, 1);

        $this->memberRepository
            ->shouldReceive('markAsMerged')
            ->once()
            ->with(2, 1, 99, Mockery::type('string'));

        $this->memberMergeRepository
            ->shouldReceive('recordMerge')
            ->once()
            ->with(
                1,
                2,
                99,
                Mockery::type('string'),
                null,
                [
                    'primary_email' => 'primary@example.com',
                    'secondary_email' => 'secondary@example.com',
                ]
            );

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->service->merge(1, 2, 99);

        $this->assertTrue(true);
    }

    private function makeMember(
        int $id,
        string $email,
        ?string $stripeCustomerId = null,
        bool $emailVerified = false,
    ): Member {
        $member = Mockery::mock(Member::class)->makePartial();

        $member->id = $id;
        $member->email = $email;
        $member->stripe_customer_id = $stripeCustomerId;
        $member->email_verified_at = $emailVerified
            ? '2026-01-01 10:00:00'
            : null;

        return $member;
    }

}