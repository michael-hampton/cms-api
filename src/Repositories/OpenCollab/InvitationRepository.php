<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;
use App\Models\Invitation;
use App\Repositories\Repository;

class InvitationRepository extends Repository
{
    /**
     * Returns true if a live (pending, non-expired, non-revoked) invitation
     * exists for the given email on the given site.
     */
    public function hasPendingInviteForEmail(string $email, int $siteId): bool
    {
        return Invitation::where('email', $email)
            ->where('site_id', $siteId)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->exists();
    }

    public function findByToken(string $token): ?Invitation
    {
        /** @var Invitation|null */
        return Invitation::where('token', $token)->first();
    }

    /**
     * Marks the invitation as used.
     *
     * @param int $id Invitation ID
     * @param int $acceptedBy User ID of whoever accepted:
     *                         - new contributor (self-service)
     *                         - admin user ID (on-behalf acceptance)
     */
    public function markAsUsed(int $id, int $acceptedBy): void
    {
        Invitation::where('id', $id)->update([
            'used_at' => date('Y-m-d H:i:s'),
            'accepted_by' => $acceptedBy,
            'accepted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function revoke(int $id, int $revokedBy): void
    {
        Invitation::where('id', $id)->update([
            'revoked_at' => date('Y-m-d H:i:s'),
            'revoked_by' => $revokedBy,
        ]);
    }

    /**
     * How many invitations for this site are currently expired (but not revoked/used).
     * Used by cron jobs for monitoring — no status column to update since
     * status is derived from expires_at at read time.
     */
    public function countExpired(int $siteId): int
    {
        return (int)Invitation::where('site_id', $siteId)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '<', date('Y-m-d H:i:s'))
            ->count();
    }

    /**
     * Returns all invitations for a site, ordered newest first.
     */
    public function getAllForSite(int $siteId): \App\Framework\Support\Collection
    {
        return Invitation::where('site_id', $siteId)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function resolveStatus(Invitation $invitation): InvitationStatus
    {
        return $invitation->resolveStatus();
    }

    protected function getModelClass(): string
    {
        return Invitation::class;
    }
}