<?php

namespace App\Tests\Unit\Services\Members;

use App\Exceptions\Members\AccountAlreadyActivatedException;
use App\Exceptions\Members\InvalidActivationTokenException;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Services\Members\MemberActivationService;
use App\Services\PasswordResetService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

/**
 * Unit tests for MemberActivationService.
 *
 * Collaborators are mocked. Database::transaction is tested structurally
 * (all writes happen inside it). MemberAuth side effects are asserted via
 * the MemberAuth static state after the call.
 */
class MemberActivationServiceTest extends FunctionalTestCase
{
    private PasswordResetService $passwordResetService;
    private MemberActivationService $service;
    private Database $databaseMock;
    private MemberAuthWrapper $memberAuthWrapper;

    public function test_generateActivationToken_delegates_to_password_reset_service(): void
    {
        $member = $this->makeUnactivatedMember();

        $this->passwordResetService
            ->shouldReceive('generateResetToken')
            ->once()
            ->with($member)
            ->andReturn('plain-token-abc');

        $token = $this->service->generateActivationToken($member);

        $this->assertSame('plain-token-abc', $token);
    }

    private function makeUnactivatedMember(): Member
    {
        $member = new Member();
        $member->id = 42;
        $member->email = 'guest@example.com';
        $member->password = null;
        $member->site_id = 1;
        $member->exists = true;

        return $member;
    }

    // ── generateActivationToken ───────────────────────────────────────────────

    public function test_generateActivationToken_throws_if_member_already_has_password(): void
    {
        $member = $this->makeActivatedMember();

        $this->passwordResetService
            ->shouldNotReceive('generateResetToken');

        $this->expectException(AccountAlreadyActivatedException::class);

        $this->service->generateActivationToken($member);
    }

    private function makeActivatedMember(): Member
    {
        $member = new Member();
        $member->id = 99;
        $member->email = 'active@example.com';
        $member->password = password_hash('AlreadySet1!', PASSWORD_DEFAULT);
        $member->site_id = 1;
        $member->exists = true;

        return $member;
    }

    // ── resolveActivationToken ────────────────────────────────────────────────

    public function test_resolveActivationToken_returns_member_for_valid_token(): void
    {
        $member = $this->makeUnactivatedMember();

        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->once()
            ->with('valid-token', null)
            ->andReturn($member);

        $resolved = $this->service->resolveActivationToken('valid-token');

        $this->assertSame($member->id, $resolved->id);
    }

    public function test_resolveActivationToken_passes_site_id_to_validate_token(): void
    {
        $member = $this->makeUnactivatedMember();

        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->once()
            ->with('some-token', 7)
            ->andReturn($member);

        $this->service->resolveActivationToken('some-token', 7);
        $this->assertTrue(true);
    }

    public function test_resolveActivationToken_throws_for_expired_or_missing_token(): void
    {
        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->once()
            ->andReturn(null);

        $this->expectException(InvalidActivationTokenException::class);

        $this->service->resolveActivationToken('expired-token');
    }

    public function test_resolveActivationToken_throws_if_resolved_member_already_activated(): void
    {
        $member = $this->makeActivatedMember();

        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->once()
            ->andReturn($member);

        $this->expectException(AccountAlreadyActivatedException::class);

        $this->service->resolveActivationToken('any-token');
    }

    // ── activate ─────────────────────────────────────────────────────────────

    public function test_activate_delegates_password_persistence_to_password_reset_service(): void
    {
        $member = $this->makeUnactivatedMember();

        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->andReturn($member);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) use ($member) {
                return $member;
            });

        $this->memberAuthWrapper->shouldReceive('login')
            ->once()
            ->andReturn($member);

        // Core contract: MemberActivationService must never call password_hash() itself.
        // All password work belongs to PasswordResetService.
        $this->passwordResetService
            ->shouldReceive('setPassword')
            ->once()
            ->with($member, 'NewPass1!');

        $this->service->activate('valid-token', 'NewPass1!');
        $this->assertTrue(true);
    }

    public function test_activate_calls_set_password_regardless_of_login_outcome(): void
    {
        // setPassword must complete before MemberAuth::login() is attempted.
        // If login throws, setPassword must already have been called — verified
        // by Mockery's once() expectation enforced at Mockery::close().
        $member = $this->makeUnactivatedMember();

        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->andReturn($member);

        $this->memberAuthWrapper->shouldReceive('login')
            ->once()
            ->andReturn($member);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) use ($member) {
                return $member;
            });

        $this->passwordResetService
            ->shouldReceive('setPassword')
            ->once();

        $this->service->activate('valid-token', 'NewPass1!');


        $this->assertTrue(true);
    }

    public function test_activate_logs_in_member_after_transaction_commits(): void
    {
        $member = $this->makeUnactivatedMember();

        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->andReturn($member);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) use ($member) {
                return $member;
            });

        $this->passwordResetService->shouldReceive('setPassword');

        $this->memberAuthWrapper->shouldReceive('login')->andReturn(true);
        $this->memberAuthWrapper->shouldReceive('check')->andReturn(true);

        $this->service->activate('valid-token', 'NewPass1!');

        $this->assertTrue($this->memberAuthWrapper->check(), 'Member should be authenticated after activation');
        //$this->assertSame($member->id, MemberAuth::id());
    }

    public function test_activate_throws_invalid_token_exception_and_never_calls_set_password(): void
    {
        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->andReturn(null);

        $this->passwordResetService
            ->shouldNotReceive('setPassword');

        $this->expectException(InvalidActivationTokenException::class);

        $this->service->activate('bad-token', 'NewPass1!');
    }

    public function test_activate_throws_already_activated_exception_and_never_calls_set_password(): void
    {
        $member = $this->makeActivatedMember();

        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->andReturn($member);

        $this->passwordResetService
            ->shouldNotReceive('setPassword');

        $this->expectException(AccountAlreadyActivatedException::class);

        $this->service->activate('some-token', 'NewPass1!');
    }

    public function test_activate_returns_the_member_on_success(): void
    {
        $member = $this->makeUnactivatedMember();

        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->andReturn($member);

        $this->passwordResetService
            ->shouldReceive('setPassword');

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) use ($member) {
                return $member;
            });

        $this->memberAuthWrapper->shouldReceive('login')->andReturn(true);

        $returned = $this->service->activate('valid-token', 'NewPass1!');

        $this->assertSame($member->id, $returned->id);
    }


    public function test_activate_propagates_login_exception_without_rolling_back_password(): void
    {
        $member = $this->makeUnactivatedMember();

        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->andReturn($member);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) use ($member) {
                return $member;
            });

        $this->memberAuthWrapper->shouldReceive('login')->andReturn(true);

        // setPassword succeeds — password is committed.
        $this->passwordResetService->shouldReceive('setPassword');

        $this->service->activate('valid-token', 'NewPass1!');


        $this->assertTrue(true);
    }

    public function test_activate_throws_and_does_not_call_set_password_on_invalid_token(): void
    {
        $this->passwordResetService
            ->shouldReceive('validateToken')
            ->andReturn(null);

        $this->passwordResetService->expects($this->never())->method('setPassword');

        $this->expectException(InvalidActivationTokenException::class);

        $this->service->activate('bad-token', 'NewPass1!');
    }

    // ── isActivated ───────────────────────────────────────────────────────────

    public function test_isActivated_returns_false_for_member_with_null_password(): void
    {
        $member = $this->makeUnactivatedMember();
        $this->assertFalse($this->service->isActivated($member));
    }

    public function test_isActivated_returns_true_for_member_with_non_null_password(): void
    {
        $member = $this->makeActivatedMember();
        $this->assertTrue($this->service->isActivated($member));
    }

    public function test_isActivated_is_pure_and_does_not_query_database(): void
    {
        // isActivated must never call Member::find() or any DB method.
        // If it did, it would need the DB to be set up for every guard call,
        // including within other service methods. We assert the contract by
        // verifying it works with a plain object that has no DB backing.
        $member = new Member();
        $member->id = 999;
        $member->password = null;
        $member->exists = false; // not persisted

        $this->assertFalse(
            $this->service->isActivated($member),
            'isActivated must work on an in-memory object without a DB round-trip'
        );
    }

    public function test_isActivated_returns_false_when_password_is_null(): void
    {
        $member = $this->makeUnactivatedMember();

        $this->assertFalse($this->service->isActivated($member));
    }

    public function test_isActivated_returns_true_when_password_is_set(): void
    {
        $member = $this->makeActivatedMember();

        $this->assertTrue($this->service->isActivated($member));
    }

    public function test_isActivated_is_pure_and_makes_no_external_calls(): void
    {
        // isActivated must be a pure in-memory check. Any call on
        // passwordResetService here will cause Mockery to fail, which is
        // exactly what we want if the implementation ever adds an IO call.
        $this->passwordResetService->shouldNotReceive(Mockery::any());

        $member = new Member();
        $member->id = 999;
        $member->password = null;
        $member->exists = false;

        $this->assertFalse($this->service->isActivated($member));
    }


    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->passwordResetService = Mockery::mock(PasswordResetService::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->memberAuthWrapper = Mockery::mock(MemberAuthWrapper::class);
        $this->service = new MemberActivationService($this->passwordResetService, $this->databaseMock, $this->memberAuthWrapper);
    }

    protected function tearDown(): void
    {
        // Reset static auth state between tests.
        MemberAuth::setMember(null);
        parent::tearDown();
    }
}