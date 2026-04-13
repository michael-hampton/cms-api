<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;
use App\Events\OpenCollab\InvitationAccepted;
use App\Exceptions\OpenCollab\InvalidInvitationException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\Model;
use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Services\OpenCollab\Notifications\InvitationAcceptedNotification;
use App\Services\OpenCollab\Notifications\InvitationCreatedNotification;
use App\Services\OpenCollab\Notifications\InvitationResentNotification;

/**
 * Orchestrates the invitation lifecycle.
 *
 * accept() changes from original:
 *   1. Finds or reuses existing user by email (handles re-invites gracefully).
 *   2. Password hashing delegated to UserRepositoryInterface::create() — the
 *      framework's AuthenticationService convention; raw hash_password()
 *      removed from this layer.
 *   3. Grants site access via SiteAccessService after user creation.
 *   4. Calls ContributorOnboardingService::start() to create the onboarding record.
 *   5. Dispatches InvitationAccepted event.
 *   6. All writes wrapped in a single transaction.
 */
class InvitationService
{
    public function __construct(
        private readonly InvitationRepository         $invitationRepository,
        private readonly UserRepositoryInterface      $userRepository,
        private readonly SiteAccessService            $siteAccessService,
        private readonly ContributorOnboardingService $onboardingService,
        private readonly EventDispatcher              $eventDispatcher,
        private readonly Database                     $database,
        private readonly NotificationDispatcher $notificationDispatcher,

    )
    {
    }

    /**
     * Admin creates an invitation for an email address.
     *
     * @throws \InvalidArgumentException if a live invitation already exists
     */
    public function create(string $email, int $invitedBy, int $siteId, int $ttlHours = 72): Model
    {
        if ($this->invitationRepository->hasPendingInviteForEmail($email, $siteId)) {
            throw new \InvalidArgumentException(
                "A pending invitation already exists for {$email}."
            );
        }

        $invitation = $this->invitationRepository->create([
            'site_id' => $siteId,
            'email' => $email,
            'token' => $this->generateToken(),
            'invited_by' => $invitedBy ?: null,
            'status' => InvitationStatus::Pending->value,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours")),
        ]);

        $this->notificationDispatcher->dispatch(
            new InvitationCreatedNotification($invitation)
        );

        return $invitation;
    }

    /**
     * Resends an existing invitation. Triggers whatever notification the
     * invitation model/mail system provides. Stub-safe: fails silently if
     * no send() infrastructure exists yet.
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
     * Guest accepts their own invitation and registers (or re-uses an existing account).
     *
     * accepted_by = the newly created (or found) user.
     *
     * @throws InvalidInvitationException
     */
    public function accept(string $token, string $name, string $password): User
    {
        $invitation = $this->invitationRepository->findByToken($token);

        if (!$invitation) {
            throw new InvalidInvitationException(InvitationStatus::Expired);
        }

        if ($invitation->resolveStatus() !== InvitationStatus::Pending) {
            throw new InvalidInvitationException($invitation->resolveStatus());
        }

        return $this->database->transaction(function () use ($invitation, $name, $password): User {

            $user = $this->userRepository->findByEmail($invitation->email);

            if (!$user) {
                $user = $this->userRepository->create([
                    'name' => $name,
                    'email' => $invitation->email,
                    'password' => $password, // repo hashes
                    'role' => 'contributor',
                    'is_contributor' => true,
                ]);
            }

            $this->siteAccessService->grantAccess(
                userId: $user->id,
                siteId: $invitation->site_id,
            );

            $this->invitationRepository->markAsUsed(
                id: $invitation->id,
                acceptedBy: $user->id,
            );

            $this->onboardingService->start($user->id, $invitation->site_id);

            // ideally after commit
            $this->eventDispatcher->dispatch(new InvitationAccepted($user, $invitation));

            $this->notificationDispatcher->dispatch(
                new InvitationAcceptedNotification($user, $invitation)
            );

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

        $invitation = $this->invitationRepository->findByToken($token);

        if (!$invitation) {
            throw new InvalidInvitationException(InvitationStatus::Expired);
        }

        if ($invitation->resolveStatus() !== InvitationStatus::Pending) {
            throw new InvalidInvitationException($invitation->resolveStatus());
        }

        return $this->database->transaction(function () use ($invitation, $name, $adminId): User {

            $user = $this->userRepository->findByEmail($invitation->email);

            if (!$user) {
                $user = $this->userRepository->create([
                    'name' => $name,
                    'email' => $invitation->email,
                    'password' => null, // or omitted entirely
                    'role' => 'contributor',
                    'is_contributor' => true,
                ]);
            }

            $this->siteAccessService->grantAccess(
                userId: $user->id,
                siteId: $invitation->site_id,
            );

            $this->invitationRepository->markAsUsed(
                id: $invitation->id,
                acceptedBy: $adminId,
            );

            $this->onboardingService->start($user->id, $invitation->site_id);

            $this->eventDispatcher->dispatch(
                new InvitationAccepted($user, $invitation, true)
            );

            $this->notificationDispatcher->dispatch(
                new InvitationAcceptedNotification($user, $invitation)
            );

            return $user;
        });
    }

    /**
     * Admin revokes an outstanding invitation.
     *
     * @throws \InvalidArgumentException if already used
     */
    public function revoke(int $invitationId, int $revokedBy): void
    {
        $invitation = $this->invitationRepository->find($invitationId);

        if (!$invitation) {
            throw new \InvalidArgumentException('Invitation not found.');
        }

        $status = $invitation->resolveStatus();

        if ($status === InvitationStatus::Used) {
            throw new \InvalidArgumentException('Cannot revoke an invitation that has already been used.');
        }

        $this->invitationRepository->revoke($invitationId, $revokedBy);
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}