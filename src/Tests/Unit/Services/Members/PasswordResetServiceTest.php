<?php

namespace App\Tests\Unit\Services\Members;

use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Framework\Authorization\EloquentTokenRepository;
use App\Services\PasswordResetService;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

/**
 * Unit tests for PasswordResetService.
 *
 * MemberRepository is the sole injected dependency — it is mocked here.
 * The service itself runs as real code in all tests except the resetPassword
 * delegation chain, where makePartial() is used because validateToken() still
 * calls Member::findByPasswordResetToken() directly (not through the repository).
 * That is a known remaining static leak — documented, not papered over.
 *
 * Sections:
 *   1. generateResetToken  — return shape, repository interaction
 *   2. validateToken       — hashing contract, repository gap noted
 *   3. setPassword         — repository reload, DB write, in-memory sync
 *   4. resetPassword       — delegation chain, return value contract
 */
class PasswordResetServiceTest extends UnitTestCase
{
    private MockInterface $memberRepository;
    private PasswordResetService $service;

    public function setUp(): void
    {

        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->service = new PasswordResetService($this->memberRepository);
    }

    public function test_generateResetToken_returns_64_char_hex_string(): void
    {
        $member = $this->makeMember();
        $persisted = $this->makePersistedMember();

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with($member->id)
            ->andReturn($persisted);

        $token = $this->service->generateResetToken($member);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $token,
            'Token must be 64 hex characters (32 random bytes)'
        );
    }

    // =========================================================================
    // 1. generateResetToken
    // =========================================================================

    private function makeMember(int $id = 1, string $email = 'test@example.com'): Member
    {
        $member = new Member();
        $member->id = $id;
        $member->email = $email;
        $member->password = null;
        $member->site_id = 1;
        $member->exists = false;

        return $member;
    }

    /**
     * A member object that behaves as if it came from the DB.
     * Used as the return value of $this->memberRepository->find().
     * Has exists = true so that ->update() does not throw.
     */
    private function makePersistedMember(int $id = 1): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;
        $member->email = 'test@example.com';
        $member->exists = true;
        $member->shouldReceive('update')
            ->byDefault()
            ->andReturnUsing(function (array $attributes) use ($member): bool {
                foreach ($attributes as $key => $value) {
                    $member->$key = $value;
                }

                return true;
            });

        return $member;
    }

    public function test_generateResetToken_returns_unique_values_on_each_call(): void
    {
        $member = $this->makeMember();
        $persisted = $this->makePersistedMember();

        $this->memberRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($persisted);

        $tokenA = $this->service->generateResetToken($member);
        $tokenB = $this->service->generateResetToken($member);

        $this->assertNotSame($tokenA, $tokenB, 'Every generated token must be unique');
    }

    public function test_generateResetToken_reloads_member_from_repository(): void
    {
        // generateResetToken reloads the member via the repository to ensure
        // it is writing to a fresh, persisted object. Assert the repository
        // is called with the correct ID.
        $member = $this->makeMember(id: 7);
        $persisted = $this->makePersistedMember(id: 7);

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with(7)
            ->andReturn($persisted);

        $this->service->generateResetToken($member);
        $this->assertTrue(true);
    }

    public function test_generateResetToken_stores_sha256_hash_not_plaintext(): void
    {
        $member = $this->makeMember();
        $persisted = $this->makePersistedMember();

        $this->memberRepository
            ->shouldReceive('find')
            ->andReturn($persisted);

        $plainToken = $this->service->generateResetToken($member);

        // The update call on $persisted sets password_reset_token.
        // We assert the stored value is the hash, not the plain token.
        $this->assertNotSame(
            $plainToken,
            $persisted->password_reset_token,
            'Plain-text token must never be written to the model'
        );
        $this->assertSame(
            hash('sha256', $plainToken),
            $persisted->password_reset_token
        );
    }

    // =========================================================================
    // 2. validateToken
    // =========================================================================

    // validateToken still calls Member::findByPasswordResetToken() directly —
    // it has not been moved to the repository yet. That means the hashing
    // contract (SHA-256 before lookup) cannot be fully unit-tested here without
    // mocking a static call. The integration tests cover this end-to-end.
    // These tests document the gap explicitly rather than working around it.

    public function test_generateResetToken_sets_expiry_approximately_one_hour_ahead(): void
    {
        $member = $this->makeMember();
        $persisted = $this->makePersistedMember();

        $this->memberRepository
            ->shouldReceive('find')
            ->andReturn($persisted);

        $before = time();
        $this->service->generateResetToken($member);
        $after = time();

        $expiry = strtotime($persisted->password_reset_expires_at);

        $this->assertGreaterThanOrEqual($before + 3600, $expiry);
        $this->assertLessThanOrEqual($after + 3602, $expiry); // 2s tolerance
    }

    // =========================================================================
    // 3. setPassword
    // =========================================================================

    public function test_validateToken_hashes_plain_token_before_lookup(): void
    {
        // validateToken calls Member::findByPasswordResetToken(hash('sha256', $token), $siteId).
        // Because that is a static model call (not through the repository), this
        // contract is verified in PasswordResetServiceIntegrationTest::
        // test_reset_token_is_never_stored_as_plaintext.
        //
        // When validateToken is moved to use $this->memberRepository->findByResetToken(),
        // this test should be written as:
        //
        //   $this->memberRepository
        //       ->shouldReceive('findByResetToken')
        //       ->once()
        //       ->with(hash('sha256', 'plain-token'), 1)
        //       ->andReturnNull();
        //
        //   $this->service->validateToken('plain-token', 1);
        $this->markTestIncomplete(
            'validateToken calls Member::findByPasswordResetToken() directly. ' .
            'Move to $this->memberRepository->findByResetToken() to make this unit-testable.'
        );
    }

    public function test_setPassword_reloads_member_from_repository(): void
    {
        $member = $this->makeMember(id: 5);
        $persisted = $this->makePersistedMember(id: 5);

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($persisted);

        $this->service->setPassword($member, 'NewPass1!');
        $this->assertTrue(true);
    }

    public function test_setPassword_syncs_hashed_password_onto_passed_in_member(): void
    {
        $member = $this->makeMember();
        $persisted = $this->makePersistedMember();

        $this->memberRepository
            ->shouldReceive('find')
            ->andReturn($persisted);

        $this->service->setPassword($member, 'Secret1!');

        $this->assertNotNull($member->password);
        $this->assertNotSame('Secret1!', $member->password, 'Plaintext must never appear on the object');
        $this->assertTrue(password_verify('Secret1!', $member->password));
    }

    public function test_setPassword_syncs_password_set_at_onto_passed_in_member(): void
    {
        $member = $this->makeMember();
        $persisted = $this->makePersistedMember();

        $this->memberRepository
            ->shouldReceive('find')
            ->andReturn($persisted);

        $before = time();
        $this->service->setPassword($member, 'Secret1!');
        $after = time();

        $this->assertNotNull($member->password_set_at);

        $ts = strtotime($member->password_set_at);
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    public function test_setPassword_syncs_null_token_onto_passed_in_member(): void
    {
        $member = $this->makeMember();
        $member->password_reset_token = hash('sha256', 'some-token');
        $member->password_reset_expires_at = '2024-01-01 13:00:00';

        $persisted = $this->makePersistedMember();

        $this->memberRepository
            ->shouldReceive('find')
            ->andReturn($persisted);

        $this->service->setPassword($member, 'Secret1!');

        $this->assertNull($member->password_reset_token, 'Token must be nulled on in-memory object');
        $this->assertNull($member->password_reset_expires_at, 'Expiry must be nulled on in-memory object');
    }

    public function test_setPassword_syncs_all_four_fields_in_a_single_call(): void
    {
        $member = $this->makeMember();
        $member->password_reset_token = 'old-token-hash';
        $member->password_reset_expires_at = '2024-01-01 10:00:00';

        $persisted = $this->makePersistedMember();

        $this->memberRepository
            ->shouldReceive('find')
            ->andReturn($persisted);

        $this->service->setPassword($member, 'AllFour1!');

        $this->assertNotNull($member->password);
        $this->assertNotNull($member->password_set_at);
        $this->assertNull($member->password_reset_token);
        $this->assertNull($member->password_reset_expires_at);
    }

    // =========================================================================
    // 4. resetPassword
    // =========================================================================

    // resetPassword delegates to validateToken (which has the static leak) and
    // setPassword. We use makePartial() so that resetPassword() runs as real
    // code while validateToken and setPassword are stubbed to isolate the
    // delegation chain from the DB.

    public function test_setPassword_only_calls_repository_find_once(): void
    {
        // A single reload is sufficient. More than one would indicate a
        // redundant DB fetch inside setPassword().
        $member = $this->makeMember();
        $persisted = $this->makePersistedMember();

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($persisted);

        $this->service->setPassword($member, 'NewPass1!');
        $this->assertTrue(true);
    }

    public function test_resetPassword_returns_false_when_token_is_invalid(): void
    {
        $tokenRepository = Mockery::mock(EloquentTokenRepository::class);
        $tokenRepository->shouldReceive('revokeTokensFor')->byDefault();
        $service = Mockery::mock(PasswordResetService::class, [$this->memberRepository, $tokenRepository])
            ->makePartial();

        $service->shouldReceive('validateToken')
            ->once()
            ->with('invalid-token', null)
            ->andReturn(null);

        $service->shouldNotReceive('setPassword');

        $result = $service->resetPassword('invalid-token', 'NewPass1!');

        $this->assertFalse($result);
    }

    public function test_resetPassword_returns_true_when_token_is_valid(): void
    {
        $member = $this->makeMember();
        $tokenRepository = Mockery::mock(EloquentTokenRepository::class);
        $tokenRepository->shouldReceive('revokeTokensFor')
            ->once()
            ->with(Member::class, $member->id, 1);
        $service = Mockery::mock(PasswordResetService::class, [$this->memberRepository, $tokenRepository])
            ->makePartial();

        $service->shouldReceive('validateToken')
            ->once()
            ->andReturn($member);

        $service->shouldReceive('setPassword')
            ->once();

        $result = $service->resetPassword('valid-token', 'NewPass1!', 1);

        $this->assertTrue($result);
    }

    public function test_resetPassword_delegates_to_set_password_with_correct_arguments(): void
    {
        $member = $this->makeMember();
        $tokenRepository = Mockery::mock(EloquentTokenRepository::class);
        $tokenRepository->shouldReceive('revokeTokensFor')
            ->once()
            ->with(Member::class, $member->id, 1);
        $service = Mockery::mock(PasswordResetService::class, [$this->memberRepository, $tokenRepository])
            ->makePartial();

        $service->shouldReceive('validateToken')
            ->andReturn($member);

        // Core contract: resetPassword must not hash the password itself.
        // It must pass the plain-text value straight through to setPassword.
        $service->shouldReceive('setPassword')
            ->once()
            ->with($member, 'NewPass1!');

        $service->resetPassword('valid-token', 'NewPass1!', 1);
        $this->assertTrue(true);
    }

    public function test_resetPassword_never_calls_set_password_when_token_is_invalid(): void
    {
        $tokenRepository = Mockery::mock(EloquentTokenRepository::class);
        $tokenRepository->shouldReceive('revokeTokensFor')->byDefault();
        $service = Mockery::mock(PasswordResetService::class, [$this->memberRepository, $tokenRepository])
            ->makePartial();

        $service->shouldReceive('validateToken')
            ->andReturn(null);

        $service->shouldNotReceive('setPassword');

        $service->resetPassword('bad-token', 'NewPass1!');
        $this->assertTrue(true);
    }

    public function test_resetPassword_passes_site_id_to_validate_token(): void
    {
        $tokenRepository = Mockery::mock(EloquentTokenRepository::class);
        $tokenRepository->shouldReceive('revokeTokensFor')->byDefault();
        $service = Mockery::mock(PasswordResetService::class, [$this->memberRepository, $tokenRepository])
            ->makePartial();

        $service->shouldReceive('validateToken')
            ->once()
            ->with('some-token', 42)
            ->andReturn(null);

        $service->shouldNotReceive('setPassword');

        $service->resetPassword('some-token', 'NewPass1!', 42);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_resetPassword_passes_null_site_id_when_none_provided(): void
    {
        $tokenRepository = Mockery::mock(EloquentTokenRepository::class);
        $tokenRepository->shouldReceive('revokeTokensFor')->byDefault();
        $service = Mockery::mock(PasswordResetService::class, [$this->memberRepository, $tokenRepository])
            ->makePartial();

        $service->shouldReceive('validateToken')
            ->once()
            ->with(Mockery::any(), null)
            ->andReturn(null);

        $service->resetPassword('any-token', 'NewPass1!');
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}