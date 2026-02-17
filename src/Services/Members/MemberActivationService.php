<?php

namespace App\Services\Members;

use App\Exceptions\Members\AccountAlreadyActivatedException;
use App\Exceptions\Members\InvalidActivationTokenException;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Services\PasswordResetService;

/**
 * Orchestrates the guest-checkout account activation flow.
 *
 * Responsibility split:
 *   - This service       → activation business rules, transaction boundary, auth
 *   - PasswordResetService → token generation, token validation, password hashing,
 *                            password persistence, password_set_at, token invalidation
 *
 * This service owns nothing security-sensitive directly. All password
 * and token operations are delegated so that hashing algorithm changes,
 * rehashing logic, and token expiry rules have exactly one place to live.
 *
 * This service MUST NOT:
 *   - Call password_hash() directly
 *   - Write password columns directly
 *   - Send emails
 *   - Know about HTTP requests or sessions
 *   - Touch order or subscription state
 */
class MemberActivationService
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly Database             $database,
        private readonly MemberAuthWrapper    $memberAuthWrapper
    )
    {
    }

    /**
     * Generate an activation token for a member who has no password.
     *
     * Delegates token creation to PasswordResetService so that the same
     * hashing, expiry, and single-use guarantees apply to both flows.
     *
     * @throws AccountAlreadyActivatedException if the member already has a password
     */
    public function generateActivationToken(Member $member): string
    {
        if ($this->isActivated($member)) {
            throw new AccountAlreadyActivatedException(
                "Member {$member->id} already has a password set. Cannot issue activation token."
            );
        }

        // Delegates entirely — same token mechanism, different entry point.
        return $this->passwordResetService->generateResetToken($member);
    }

    /**
     * Return whether a member has set a password.
     *
     * Pure in-memory check — no DB query.
     *
     * Callers are responsible for passing a fresh member object when
     * stale state would be a problem. This method does not silently
     * re-fetch from the DB.
     */
    public function isActivated(Member $member): bool
    {
        return $member->password !== null;
    }

    /**
     * Activate the account: set the password and log the member in.
     *
     * Transaction boundary:
     *   The transaction contains only the DB write (delegated to
     *   PasswordResetService::setPassword). MemberAuth::login() runs after
     *   the transaction commits — it is a session side effect, not a DB
     *   write, and must not be inside the transaction boundary.
     *
     * If MemberAuth::login() throws (e.g. session failure), the exception
     * propagates to the controller. The password IS already set at that
     * point — the member can log in manually. The controller decides how
     * to surface this.
     *
     * @throws InvalidActivationTokenException  if the token is invalid/expired
     * @throws AccountAlreadyActivatedException if the member already has a password
     */
    public function activate(string $token, string $newPassword, ?int $siteId = null): Member
    {
        // Resolve and guard before opening the transaction.
        // validateToken already performs a fresh DB fetch, so $member is
        // not stale at this point.
        $member = $this->resolveActivationToken($token, $siteId);

        // Transaction contains one DB write only.
        // PasswordResetService::setPassword() owns:
        //   - password_hash()
        //   - password_set_at
        //   - token nulling (single-use guarantee)
        $member = $this->database->transaction(function () use ($member, $newPassword) {
            $this->passwordResetService->setPassword($member, $newPassword);

            Logger::info('Account activated via guest checkout flow', [
                'member_id' => $member->id,
                'site_id' => $member->site_id,
            ]);

            return $member;
        });

        // Outside the transaction. Session writes are not DB operations.
        // Exception propagates to the controller if login fails.
        $this->memberAuthWrapper->login($member);

        return $member;
    }

    /**
     * Resolve the member behind an activation token.
     *
     * Validates the token via PasswordResetService and asserts the flow is
     * appropriate. Returns the member so the controller can render context.
     *
     * The caller is responsible for reloading the member if freshness is
     * required between this call and activate(). This method does not
     * perform an extra DB fetch — it trusts the object returned by the
     * token lookup, which is already fresh from the DB.
     *
     * @throws InvalidActivationTokenException  if the token is expired or not found
     * @throws AccountAlreadyActivatedException if the resolved member is already active
     */
    public function resolveActivationToken(string $token, ?int $siteId = null): Member
    {
        $member = $this->passwordResetService->validateToken($token, $siteId);

        if (!$member) {
            throw new InvalidActivationTokenException(
                'The activation link is invalid or has expired.'
            );
        }

        if ($this->isActivated($member)) {
            throw new AccountAlreadyActivatedException(
                "Member {$member->id} already has a password. Redirect to login."
            );
        }

        return $member;
    }
}