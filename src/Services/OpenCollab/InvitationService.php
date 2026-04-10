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
 *   - Accepting invitations (registers a contributor user)
 *
 * Two writes in accept() → wrapped in a transaction.
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
     * Admin revokes an outstanding invitation.
     *
     * @throws \InvalidArgumentException if the invitation does not exist or is already used
     */
    public function revoke(int $invitationId, int $revokedBy): void
    {
        $invitation = $this->invitationRepository->find($invitationId);

        if (!$invitation) {
            throw new \InvalidArgumentException("Invitation not found.");
        }

        $status = $this->invitationRepository->resolveStatus($invitation);

        if ($status === InvitationStatus::Used) {
            throw new \InvalidArgumentException("Cannot revoke an invitation that has already been used.");
        }

        $this->invitationRepository->revoke($invitationId, $revokedBy);
    }

    /**
     * Guest accepts an invitation and becomes a contributor user.
     *
     * Wraps two writes (create user + mark invite used) in a transaction.
     * Returns the newly created User so the controller can immediately issue a token.
     *
     * @throws InvalidInvitationException with specific reason (Expired, Revoked, Used)
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

            $this->invitationRepository->markAsUsed($invitation->id);

            return $user;
        });
    }

    /**
     * Admin creates an invitation for a given email address.
     *
     * @throws \InvalidArgumentException if a live invitation already exists for this email
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

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}