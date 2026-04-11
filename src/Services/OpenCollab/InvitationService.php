<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;
use App\Exceptions\OpenCollab\InvalidInvitationException;
use App\Framework\Database\Database;
use App\Models\Model;
use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\InvitationRepository;

/**
 * Orchestrates the invitation lifecycle.
 *
 * Responsibilities:
 *   - Creating invitations (admin action)
 *   - Revoking invitations (admin action)
 *   - Accepting invitations (self-service: guest registers themselves)
 *   - Admin accepting on behalf (admin creates the user for an invitee)
 *
 * Both acceptance paths record accepted_by so there is always a clear
 * audit trail of who consumed a given invitation token.
 */
class InvitationService
{
    public function __construct(
        private readonly InvitationRepository    $invitationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly Database                $database,
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

        return $this->invitationRepository->create([
            'site_id' => $siteId,
            'email' => $email,
            'token' => $this->generateToken(),
            'invited_by' => $invitedBy,
            'status' => InvitationStatus::Pending->value,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours")),
        ]);
    }

    /**
     * Guest accepts their own invitation and registers.
     *
     * accepted_by = the newly created user (self-acceptance).
     *
     * @throws InvalidInvitationException
     */
    public function accept(string $token, string $name, string $password): User
    {
        $invitation = $this->invitationRepository->findByToken($token);

        if (!$invitation) {
            throw new InvalidInvitationException(InvitationStatus::Expired);
        }

        $status = $invitation->resolveStatus();

        if ($status !== InvitationStatus::Pending) {
            throw new InvalidInvitationException($status);
        }

        return $this->database->transaction(function () use ($invitation, $name, $password): User {
            $user = $this->userRepository->create([
                'site_id' => $invitation->site_id,
                'name' => $name,
                'email' => $invitation->email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'contributor',
                'is_contributor' => true,
            ]);

            // Record who accepted and when
            $this->invitationRepository->markAsUsed(
                id: $invitation->id,
                acceptedBy: $user->id,
            );

            return $user;
        });
    }

    /**
     * Admin accepts an invitation on behalf of the invitee.
     *
     * Used when an admin wants to provision an account without requiring
     * the invitee to go through the self-service flow.
     *
     * accepted_by = the admin user ID (not the new contributor).
     *
     * @throws InvalidInvitationException
     * @throws \InvalidArgumentException  if adminId is not provided
     */
    public function acceptOnBehalf(string $token, string $name, string $temporaryPassword, int $adminId): User
    {
        $invitation = $this->invitationRepository->findByToken($token);

        if (!$invitation) {
            throw new InvalidInvitationException(InvitationStatus::Expired);
        }

        $status = $invitation->resolveStatus();

        if ($status !== InvitationStatus::Pending) {
            throw new InvalidInvitationException($status);
        }

        return $this->database->transaction(function () use ($invitation, $name, $temporaryPassword, $adminId): User {
            $user = $this->userRepository->create([
                'site_id' => $invitation->site_id,
                'name' => $name,
                'email' => $invitation->email,
                'password' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                'role' => 'contributor',
                'is_contributor' => true,
            ]);

            // accepted_by = admin who created the account on behalf
            $this->invitationRepository->markAsUsed(
                id: $invitation->id,
                acceptedBy: $adminId,
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