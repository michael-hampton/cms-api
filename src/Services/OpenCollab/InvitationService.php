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
 * Key rule: hasPendingInviteForEmail guards against duplicate *live* invitations,
 * but does NOT block re-inviting an email that already has a user account.
 * When a user already exists for the email, we still create the invitation
 * (the user may need to re-accept after account closure / re-onboarding).
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
     * @throws \InvalidArgumentException if a live (pending, non-expired) invitation already exists
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
     * If a user account already exists for this email, the existing account
     * is linked rather than creating a duplicate. This supports re-onboarding
     * after account closure.
     *
     * accepted_by = the user's ID (self-acceptance).
     *
     * @throws InvalidInvitationException
     */
    public function accept(string $token, string $name, string $password, int $siteId): User
    {
        $invitation = $this->invitationRepository->findByToken($token);

        if (!$invitation) {
            throw new InvalidInvitationException(InvitationStatus::Expired);
        }

        $status = $invitation->resolveStatus();

        if ($status !== InvitationStatus::Pending) {
            throw new InvalidInvitationException($status);
        }

        return $this->database->transaction(function () use ($invitation, $name, $password, $siteId): User {
            // Check if a user already exists for this email (re-invitation scenario)
            $existing = $this->userRepository->findByEmail($invitation->email, $siteId);

            if ($existing) {
                // Re-activate the existing account and update the name/password
                $this->userRepository->update($existing->id, [
                    'name' => $name,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'is_active' => true,
                    'is_contributor' => true,
                    'role' => 'contributor',
                ]);

                $user = $this->userRepository->find($existing->id);
            } else {
                $user = $this->userRepository->create([
                    'site_id' => $invitation->site_id,
                    'name' => $name,
                    'email' => $invitation->email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'contributor',
                    'is_contributor' => true,
                ]);
            }

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
     * @throws InvalidInvitationException
     */
    public function acceptOnBehalf(string $token, string $name, string $temporaryPassword, int $adminId, int $siteId): User
    {
        $invitation = $this->invitationRepository->findByToken($token);

        if (!$invitation) {
            throw new InvalidInvitationException(InvitationStatus::Expired);
        }

        $status = $invitation->resolveStatus();

        if ($status !== InvitationStatus::Pending) {
            throw new InvalidInvitationException($status);
        }

        return $this->database->transaction(function () use ($invitation, $name, $temporaryPassword, $adminId, $siteId): User {
            $existing = $this->userRepository->findByEmail($invitation->email, $siteId);

            if ($existing) {
                $this->userRepository->update($existing->id, [
                    'name' => $name,
                    'password' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    'is_active' => true,
                    'is_contributor' => true,
                    'role' => 'contributor',
                ]);
                $user = $this->userRepository->find($existing->id);
            } else {
                $user = $this->userRepository->create([
                    'site_id' => $invitation->site_id,
                    'name' => $name,
                    'email' => $invitation->email,
                    'password' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    'role' => 'contributor',
                    'is_contributor' => true,
                ]);
            }

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