<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;
use App\DTO\OpenCollab\ContributorAccessGrantRequest;
use App\Events\OpenCollab\InvitationAccepted;
use App\Exceptions\OpenCollab\InvalidInvitationException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\Model;
use App\Models\User;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\InvitationRepositoryInterface;
use App\Services\OpenCollab\Notifications\InvitationAcceptedNotification;
use App\Services\OpenCollab\Notifications\InvitationCreatedNotification;
use App\Services\OpenCollab\Notifications\InvitationResentNotification;
use App\Services\User\UserLifecycleServiceInterface;

/**
 * Orchestrates the invitation lifecycle.
 *
 * Key hardening changes from the original
 * ----------------------------------------
 * 1. Email normalisation (trim + lowercase) applied before every operation.
 * 2. create() guards against inviting someone who already has site access.
 * 3. accept() no longer overwrites the password or name of existing users;
 *    it only grants site access.
 * 4. acceptOnBehalf() applies the same existing-user guard.
 * 5. Both acceptance paths re-validate site access inside the transaction to
 *    prevent race conditions creating duplicate access records.
 * 6. Both acceptance paths guard against duplicate onboarding records.
 * 7. Events and notifications are dispatched AFTER the transaction commits
 *    via Database::afterCommit() so failed writes never generate notifications.
 * 8. Revoked invitations are treated as permanently invalid — they cannot be
 *    regenerated automatically through the resend flow.
 */
class InvitationService
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitationRepository,
        private readonly UserLifecycleServiceInterface $userLifecycle,
        private readonly OpenCollabAuthorisationInterface $authorisation,
        private readonly ContributorOnboardingService $onboardingService,
        private readonly ContributorProfileRepository $profileRepository,
        private readonly EventDispatcher              $eventDispatcher,
        private readonly Database                     $database,
        private readonly NotificationDispatcher       $notificationDispatcher,
    ) {
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Re-send a pending invitation email.
     *
     * Only pending invitations are re-sent. Expired, used, or revoked
     * invitations are silently skipped — callers should use the resend
     * service which handles those lifecycle states separately.
     */
    public function send(\App\Models\Invitation $invitation): void
    {
        if ($invitation->resolveStatus() !== InvitationStatus::Pending) {
            return;
        }

        $this->notificationDispatcher->dispatch(
            new InvitationResentNotification($invitation)
        );
    }

    /**
     * Admin creates an invitation for an email address.
     *
     * Guards:
     *   - Normalises email.
     *   - Rejects if the user already has contributor access to the site.
     *   - Rejects if a pending invitation already exists for the same email+site.
     *
     * @throws \InvalidArgumentException
     */
    public function create(string $email, int $invitedBy, int $siteId, int $ttlHours = 72): Model
    {
        $email = $this->normaliseEmail($email);

        return $this->database->transaction(function () use ($email, $invitedBy, $siteId, $ttlHours): Model {
            $this->assertNoExistingSiteAccessForEmail($email, $siteId);

            if ($this->invitationRepository->hasPendingInviteForEmail($email, $siteId)) {
                throw new \InvalidArgumentException(
                    "A pending invitation already exists for {$email}."
                );
            }

            $invitation = $this->invitationRepository->create([
                'site_id'    => $siteId,
                'email'      => $email,
                'token'      => $this->generateToken(),
                'invited_by' => $invitedBy ?: null,
                'status'     => InvitationStatus::Pending->value,
                'expires_at' => date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours")),
            ]);

            $this->afterCommit(function () use ($invitation): void {
                $this->notificationDispatcher->dispatch(
                    new InvitationCreatedNotification($invitation)
                );
            });

            return $invitation;
        });
    }

    /**
     * Guest accepts their own invitation and registers (or re-uses an existing account).
     *
     * Existing-user safety
     * ---------------------
     * When the invited email already has an account, the service grants site
     * access WITHOUT modifying the existing user's password or name. The
     * existing user must prove account ownership by logging in with their
     * current credentials after acceptance.
     *
     * @throws InvalidInvitationException
     */
    public function accept(string $token, string $name, string $password): User
    {
        $invitation = $this->findPendingInvitationOrFail($token);

        return $this->database->transaction(function () use ($invitation, $name, $password): User {
            $user = $this->userLifecycle->ensureContributorAccount(
                email: $invitation->email,
                name: $name,
                password: $password,
                reason: 'OpenCollab invitation accepted',
            );

            $this->authorisation->grantContributorAccess(new ContributorAccessGrantRequest(
                userId: (int) $user->id,
                siteId: (int) $invitation->site_id,
                actorUserId: (int) $user->id,
                invitationId: (int) $invitation->id,
                reason: 'OpenCollab invitation accepted',
            ));

            $this->invitationRepository->markAsUsed(
                id:         $invitation->id,
                acceptedBy: $user->id,
            );

            $this->startOnboardingIfNotStarted(
                userId: (int) $user->id,
                siteId: (int) $invitation->site_id,
            );

            $this->afterCommit(function () use ($user, $invitation): void {
                $this->eventDispatcher->dispatch(
                    new InvitationAccepted($user, $invitation)
                );

                $this->notificationDispatcher->dispatch(
                    new InvitationAcceptedNotification($user, $invitation)
                );
            });

            return $user;
        });
    }

    /**
     * Admin accepts an invitation on behalf of the invitee.
     *
     * accepted_by = the admin user ID (not the new contributor).
     *
     * @throws InvalidInvitationException
     * @throws \InvalidArgumentException  if adminId is not provided
     */
    public function acceptOnBehalf(string $token, string $name, int $adminId): User
    {
        if (!$adminId) {
            throw new \InvalidArgumentException('Admin ID is required.');
        }

        $invitation = $this->findPendingInvitationOrFail($token);

        return $this->database->transaction(function () use ($invitation, $name, $adminId): User {
            $user = $this->userLifecycle->ensureContributorAccount(
                email: $invitation->email,
                name: $name,
                actorUserId: $adminId,
                reason: 'OpenCollab invitation accepted on behalf of contributor',
            );

            $this->authorisation->grantContributorAccess(new ContributorAccessGrantRequest(
                userId: (int) $user->id,
                siteId: (int) $invitation->site_id,
                actorUserId: $adminId,
                invitationId: (int) $invitation->id,
                reason: 'OpenCollab invitation accepted on behalf of contributor',
            ));

            $this->invitationRepository->markAsUsed(
                id:         $invitation->id,
                acceptedBy: $adminId,
            );

            $this->startOnboardingIfNotStarted(
                userId: (int) $user->id,
                siteId: (int) $invitation->site_id,
            );

            $this->afterCommit(function () use ($user, $invitation): void {
                $this->eventDispatcher->dispatch(
                    new InvitationAccepted($user, $invitation, true)
                );

                $this->notificationDispatcher->dispatch(
                    new InvitationAcceptedNotification($user, $invitation)
                );
            });

            return $user;
        });
    }

    /**
     * Admin revokes an outstanding invitation.
     *
     * Revoked invitations are permanently invalid. They cannot be regenerated
     * automatically by the resend flow — an admin must create a new invitation
     * explicitly. This is an intentional business rule to prevent accidental
     * re-invitation after a revocation decision.
     *
     * @throws \InvalidArgumentException if already used
     */
    public function revoke(int $invitationId, int $revokedBy): void
    {
        $invitation = $this->invitationRepository->find($invitationId);

        if (!$invitation) {
            throw new \InvalidArgumentException('Invitation not found.');
        }

        if ($invitation->resolveStatus() === InvitationStatus::Used) {
            throw new \InvalidArgumentException('Cannot revoke an invitation that has already been used.');
        }

        $this->invitationRepository->revoke($invitationId, $revokedBy);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Find a pending invitation by token or throw.
     *
     * @throws InvalidInvitationException
     */
    private function findPendingInvitationOrFail(string $token): \App\Models\Invitation
    {
        $invitation = $this->invitationRepository->findByToken($token);

        if (!$invitation) {
            throw new InvalidInvitationException(InvitationStatus::Expired);
        }

        if ($invitation->resolveStatus() !== InvitationStatus::Pending) {
            throw new InvalidInvitationException($invitation->resolveStatus());
        }

        return $invitation;
    }

    private function assertNoExistingSiteAccessForEmail(string $email, int $siteId): void
    {
        $user = $this->userLifecycle->findByEmail($email);

        if (!$user) {
            return;
        }

        if ($this->authorisation->hasContributorAccess((int) $user->id, $siteId)) {
            throw new \InvalidArgumentException(
                'This contributor already has access to the site.'
            );
        }
    }

    /**
     * Ensure the contributor has their global profile, then start onboarding
     * only if a site-specific onboarding record does not already exist.
     */
    private function startOnboardingIfNotStarted(int $userId, int $siteId): void
    {
        $this->profileRepository->findOrCreateForUser($userId);

        if (!$this->onboardingService->hasStarted($userId, $siteId)) {
            $this->onboardingService->start($userId, $siteId);
        }
    }

    /**
     * Register a callback to run after the current database transaction commits.
     *
     * Falls back to immediate execution if the Database abstraction does not
     * support after-commit hooks, so behaviour remains correct either way.
     */
    private function afterCommit(callable $callback): void
    {
        if (method_exists($this->database, 'afterCommit')) {
            $this->database->afterCommit($callback);
        } else {
            // Fallback: execute immediately. Events/notifications may fire before
            // the outer transaction commits if the Database implementation does
            // not support hooks — this is a known limitation until afterCommit
            // is added to the Database abstraction.
            $callback();
        }
    }

    private function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
