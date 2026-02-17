<?php

namespace App\Tests\Unit\Services\Members;

use App\Exceptions\Members\AccountAlreadyActivatedException;
use App\Exceptions\Members\InvalidActivationTokenException;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Container;
use App\Models\Member;
use App\Models\Model;
use App\Services\Members\MemberActivationService;
use App\Services\PasswordResetService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

/**
 * Integration tests for the guest checkout → account activation flow.
 *
 * Tests the full stack — real DB, real services, no mocks.
 * Asserts on final persisted state and auth state.
 *
 * Out of scope here:
 *   - Event emission and listener wiring (see ListenerIntegrationTest)
 *   - Email sending (see SendAccountActivationEmailListenerTest)
 *   - Controller HTTP layer (see AccountActivationControllerTest)
 *
 * Sections:
 *   1. Token lifecycle
 *   2. Password persistence
 *   3. Authentication after activation
 *   4. Negative / edge cases
 */
class AccountActivationFlowTest extends FunctionalTestCase
{
    private MemberActivationService $activationService;
    private PasswordResetService $passwordResetService;

    public function test_activation_token_can_be_generated_for_unactivated_member(): void
    {
        $member = $this->createUnactivatedMember();

        $token = $this->activationService->generateActivationToken($member);

        $this->assertNotEmpty($token);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    private function createUnactivatedMember(string $email = 'guest@example.com'): Model
    {
        return Member::create([
            'site_id' => 1,
            'email' => $email,
            'password' => null,
            'first_name' => 'Guest',
            'last_name' => 'User',
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // 1. Token lifecycle
    // =========================================================================

    public function test_generated_token_is_resolvable(): void
    {
        $member = $this->createUnactivatedMember();

        $token = $this->activationService->generateActivationToken($member);
        $resolved = $this->activationService->resolveActivationToken($token, $this->siteId);

        $this->assertSame($member->id, $resolved->id);
    }

    public function test_activation_token_is_stored_hashed_never_plaintext(): void
    {
        $member = $this->createUnactivatedMember();
        $plainToken = $this->activationService->generateActivationToken($member);

        $fresh = Member::find($member->id);

        $this->assertNotSame(
            $plainToken,
            $fresh->password_reset_token,
            'Plain-text token must never be stored in the database'
        );
        $this->assertSame(
            hash('sha256', $plainToken),
            $fresh->password_reset_token
        );
    }

    public function test_token_is_single_use_and_invalidated_after_successful_activation(): void
    {
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);

        $this->activationService->activate($token, 'NewPass1!', $this->siteId);

        $this->expectException(InvalidActivationTokenException::class);

        $this->activationService->resolveActivationToken($token, $this->siteId);
    }

    public function test_expired_token_cannot_be_resolved(): void
    {
        $member = $this->createUnactivatedMember();

        $member->update([
            'password_reset_token' => hash('sha256', 'expired-token'),
            'password_reset_expires_at' => date('Y-m-d H:i:s', strtotime('-1 second')),
        ]);

        $this->expectException(InvalidActivationTokenException::class);

        $this->activationService->resolveActivationToken('expired-token', $this->siteId);
    }

    public function test_token_resolves_to_the_correct_member_when_multiple_members_exist(): void
    {
        $memberA = $this->createUnactivatedMember('a@example.com');
        $memberB = $this->createUnactivatedMember('b@example.com');

        $tokenForA = $this->activationService->generateActivationToken($memberA);
        $tokenForB = $this->activationService->generateActivationToken($memberB);

        $resolvedA = $this->activationService->resolveActivationToken($tokenForA, $this->siteId);
        $resolvedB = $this->activationService->resolveActivationToken($tokenForB, $this->siteId);

        $this->assertSame($memberA->id, $resolvedA->id);
        $this->assertSame($memberB->id, $resolvedB->id);
        $this->assertNotSame($resolvedA->id, $resolvedB->id);
    }

    public function test_activate_persists_hashed_password_to_database(): void
    {
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);

        $this->activationService->activate($token, 'NewPass1!', $this->siteId);

        $fresh = Member::find($member->id);

        $this->assertNotNull($fresh->password);
        $this->assertNotSame(
            'NewPass1!',
            $fresh->password,
            'Password must be hashed at rest — never stored plaintext'
        );
        $this->assertTrue(password_verify('NewPass1!', $fresh->password));
    }

    // =========================================================================
    // 2. Password persistence
    // =========================================================================

    public function test_activate_records_password_set_at_within_expected_window(): void
    {
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);

        $before = time();
        $this->activationService->activate($token, 'NewPass1!', $this->siteId);
        $after = time();

        $fresh = Member::find($member->id);

        $this->assertNotNull($fresh->password_set_at);

        $ts = strtotime($fresh->password_set_at);
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    public function test_activate_nulls_token_columns_after_password_is_set(): void
    {
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);

        $this->activationService->activate($token, 'NewPass1!', $this->siteId);

        $fresh = Member::find($member->id);

        $this->assertNull($fresh->password_reset_token, 'Token column must be nulled');
        $this->assertNull($fresh->password_reset_expires_at, 'Expiry column must be nulled');
    }

    public function test_activate_logs_in_the_member_automatically(): void
    {
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);

        $this->activationService->activate($token, 'NewPass1!', $this->siteId);

        $this->assertTrue(MemberAuth::check());
        $this->assertSame($member->id, MemberAuth::id());
    }

    // =========================================================================
    // 3. Authentication after activation
    // =========================================================================

    public function test_activate_returns_the_member_on_success(): void
    {
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);

        $returned = $this->activationService->activate($token, 'NewPass1!', $this->siteId);

        $this->assertInstanceOf(Member::class, $returned);
        $this->assertSame($member->id, $returned->id);
    }

    public function test_cannot_generate_token_for_member_who_already_has_a_password(): void
    {
        $member = $this->createActivatedMember();

        $this->expectException(AccountAlreadyActivatedException::class);

        $this->activationService->generateActivationToken($member);
    }

    // =========================================================================
    // 4. Negative / edge cases
    // =========================================================================

    private function createActivatedMember(string $email = 'active@example.com'): Member
    {
        return Member::create([
            'site_id' => 1,
            'email' => $email,
            'password' => password_hash('Password1!', PASSWORD_DEFAULT),
            'password_set_at' => date('Y-m-d H:i:s'),
            'first_name' => 'Active',
            'last_name' => 'Member',
            'is_active' => true,
        ]);
    }

    public function test_cannot_resolve_a_completely_invalid_token(): void
    {
        $this->expectException(InvalidActivationTokenException::class);

        $this->activationService->resolveActivationToken('not-a-real-token', $this->siteId);
    }

    public function test_cannot_activate_when_member_already_has_password_set_after_token_was_issued(): void
    {
        // Token was valid at issuance. Password was set externally between
        // token generation and activation attempt.
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);

        $member->update(['password' => password_hash('SetExternally1!', PASSWORD_DEFAULT)]);

        $this->expectException(AccountAlreadyActivatedException::class);

        $this->activationService->activate($token, 'NewPass1!', $this->siteId);
    }

    public function test_reused_token_is_rejected_after_successful_activation(): void
    {
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);

        $this->activationService->activate($token, 'NewPass1!', $this->siteId);

        // After activation, setPassword() nulls the token columns (single-use guarantee).
        // A second activate() call with the same token will fail at validateToken()
        // — the hash no longer exists in the DB — so resolveActivationToken() throws
        // InvalidActivationTokenException, not AccountAlreadyActivatedException.
        // The token is gone before we ever reach the isActivated() guard.
        $this->expectException(InvalidActivationTokenException::class);

        $this->activationService->activate($token, 'DifferentPass2!', $this->siteId);
    }

    public function test_password_is_never_stored_as_plaintext_in_any_column(): void
    {
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);
        $plainPass = 'NeverStoreMe99!';

        $this->activationService->activate($token, $plainPass, $this->siteId);

        $fresh = Member::find($member->id);

        $this->assertNotSame($plainPass, $fresh->password);
        $this->assertNotSame($plainPass, $fresh->password_reset_token);
    }

    public function test_password_set_at_is_null_before_activation(): void
    {
        $member = $this->createUnactivatedMember();

        $fresh = Member::find($member->id);

        $this->assertNull(
            $fresh->password_set_at,
            'password_set_at must be null until activation completes'
        );
    }

    public function test_isActivated_returns_false_for_unactivated_member(): void
    {
        $member = $this->createUnactivatedMember();

        $this->assertFalse($this->activationService->isActivated($member));
    }

    public function test_isActivated_returns_true_after_activation(): void
    {
        $member = $this->createUnactivatedMember();
        $token = $this->activationService->generateActivationToken($member);

        $this->activationService->activate($token, 'NewPass1!', $this->siteId);

        // Reload to pick up the committed state.
        $fresh = Member::find($member->id);

        $this->assertTrue($this->activationService->isActivated($fresh), $this->siteId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        $this->passwordResetService = Container::getInstance()->make(PasswordResetService::class);
        $this->activationService = Container::getInstance()->make(MemberActivationService::class);

        MemberAuth::setMember(null);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        MemberAuth::setMember(null);
        parent::tearDown();
    }
}