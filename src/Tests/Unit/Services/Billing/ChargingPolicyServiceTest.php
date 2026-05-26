<?php

namespace App\Tests\Unit\Services\Billing;

use App\Framework\Database\Database;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\ChargingPolicyService;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ChargingPolicyServiceTest extends TestCase
{
    private MemberRepository&MockInterface $memberRepository;
    private Database&MockInterface         $database;
    private ChargingPolicyService          $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->database         = Mockery::mock(Database::class);

        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new ChargingPolicyService(
            $this->memberRepository,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── assertChargingAllowed ─────────────────────────────────────────────────

    public function test_assert_charging_allowed_does_not_throw_when_charging_is_enabled(): void
    {
        $member                    = $this->makeMember();
        $member->charging_disabled = false;

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);

        $this->service->assertChargingAllowed(1); // must not throw

        $this->addToAssertionCount(1);
    }

    public function test_assert_charging_allowed_throws_when_member_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Member not found');

        $this->service->assertChargingAllowed(99);
    }

    public function test_assert_charging_allowed_throws_when_charging_disabled_without_reason(): void
    {
        $member                            = $this->makeMember();
        $member->charging_disabled         = true;
        $member->charging_disabled_reason  = null;

        $this->memberRepository->shouldReceive('find')->andReturn($member);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Charging is disabled for this member');

        $this->service->assertChargingAllowed(1);
    }

    public function test_assert_charging_allowed_includes_reason_in_exception_message(): void
    {
        $member                           = $this->makeMember();
        $member->charging_disabled        = true;
        $member->charging_disabled_reason = 'Fraud suspected';

        $this->memberRepository->shouldReceive('find')->andReturn($member);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Fraud suspected');

        $this->service->assertChargingAllowed(1);
    }

    // ── isChargingDisabled ────────────────────────────────────────────────────

    public function test_is_charging_disabled_returns_false_when_charging_enabled(): void
    {
        $member                    = $this->makeMember();
        $member->charging_disabled = false;

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);

        $this->assertFalse($this->service->isChargingDisabled(1));
    }

    public function test_is_charging_disabled_returns_true_when_charging_disabled(): void
    {
        $member                    = $this->makeMember();
        $member->charging_disabled = true;

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);

        $this->assertTrue($this->service->isChargingDisabled(1));
    }

    public function test_is_charging_disabled_returns_false_when_member_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->assertFalse($this->service->isChargingDisabled(99));
    }

    // ── disableCharging ───────────────────────────────────────────────────────

    public function test_disable_charging_updates_member_and_returns_refreshed_record(): void
    {
        $member        = $this->makeMember();
        $updatedMember = $this->makeMember(['charging_disabled' => true]);

        // find() is called twice with the same id: first to load, then to refresh.
        // A single expectation with andReturn($a, $b) returns them in sequence.
        $this->memberRepository
            ->shouldReceive('find')
            ->with(1)
            ->twice()
            ->andReturn($member, $updatedMember);

        $this->memberRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::subset([
                'charging_disabled'        => true,
                'charging_disabled_by'     => 5,
                'charging_disabled_reason' => 'Disputed charge',
            ]));

        $result = $this->service->disableCharging(1, 5, 'Disputed charge');

        $this->assertSame($updatedMember, $result);
    }

    public function test_disable_charging_wraps_in_a_transaction(): void
    {
        $member        = $this->makeMember();
        $updatedMember = $this->makeMember(['charging_disabled' => true]);

        $this->memberRepository->shouldReceive('find')->andReturn($member, $updatedMember);
        $this->memberRepository->shouldReceive('update');

        $this->service->disableCharging(1, 5);

        $this->database->shouldHaveReceived('transaction')->once();

        $this->assertTrue(true);
    }

    public function test_disable_charging_throws_when_member_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Member not found');

        $this->service->disableCharging(99, 1);
    }

    public function test_disable_charging_stores_null_reason_when_none_provided(): void
    {
        $member        = $this->makeMember();
        $updatedMember = $this->makeMember(['charging_disabled' => true]);

        $this->memberRepository->shouldReceive('find')->andReturn($member, $updatedMember);
        $this->memberRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::subset(['charging_disabled_reason' => null]));

        $this->service->disableCharging(1, 5, null);

        $this->addToAssertionCount(1);
    }

    // ── enableCharging ────────────────────────────────────────────────────────

    public function test_enable_charging_clears_all_disabled_fields_and_returns_refreshed_record(): void
    {
        $member        = $this->makeMember(['charging_disabled' => true]);
        $updatedMember = $this->makeMember(['charging_disabled' => false]);

        // find() is called twice with the same id: first to load, then to refresh.
        $this->memberRepository
            ->shouldReceive('find')
            ->with(1)
            ->twice()
            ->andReturn($member, $updatedMember);

        $this->memberRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::subset([
                'charging_disabled'        => false,
                'charging_disabled_reason' => null,
                'charging_disabled_at'     => null,
                'charging_disabled_by'     => null,
            ]));

        $result = $this->service->enableCharging(1, 7);

        $this->assertSame($updatedMember, $result);
    }

    public function test_enable_charging_wraps_in_a_transaction(): void
    {
        $member        = $this->makeMember();
        $updatedMember = $this->makeMember();

        $this->memberRepository->shouldReceive('find')->andReturn($member, $updatedMember);
        $this->memberRepository->shouldReceive('update');

        $this->service->enableCharging(1, 7);

        $this->database->shouldHaveReceived('transaction')->once();

        $this->assertTrue(true);
    }

    public function test_enable_charging_throws_when_member_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Member not found');

        $this->service->enableCharging(99, 7);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeMember(array $props = []): Member
    {
        $member                    = Mockery::mock(Member::class)->makePartial();
        $member->id                = 1;
        $member->charging_disabled = $props['charging_disabled'] ?? false;
        $member->charging_disabled_reason = $props['charging_disabled_reason'] ?? null;

        return $member;
    }
}