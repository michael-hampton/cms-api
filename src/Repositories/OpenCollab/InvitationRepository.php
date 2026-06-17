<?php

namespace App\Repositories\OpenCollab;

use App\Models\Invitation;
use App\Repositories\Repository;

class InvitationRepository extends Repository implements InvitationRepositoryInterface
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
     * Supersedes all non-used invitations for an email on a site by marking
     * them as expired. Used before creating a replacement invitation during
     * a self-service resend of an expired/revoked link.
     */
    public function expireAllForEmail(string $email, int $siteId): void
    {
        Invitation::where('email', $email)
            ->where('site_id', $siteId)
            ->whereNull('used_at')
            ->update([
                'expires_at' => date('Y-m-d H:i:s'), // set expiry to now → effectively expired
            ]);
    }

    /**
     * Lightweight rate limiting helper.
     * Returns the number of invitations created for this email in the last hour.
     * Used by ResendInvitationController to prevent abuse without Redis.
     */
    public function recentResendCount(string $email, int $siteId): int
    {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

        return (int)Invitation::where('email', $email)
            ->where('site_id', $siteId)
            ->where('created_at', '>=', $oneHourAgo)
            ->count();
    }

    /**
     * How many invitations for this site are currently expired (but not revoked/used).
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

    public function findLatestForEmail(string $email, int $siteId): ?Invitation
    {
        return $this->model
            ->where('email', $email)
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Returns all invitations for a specific email on a site, ordered newest first.
     * More efficient than loading all site invitations and filtering in PHP.
     */
    public function getAllForEmail(string $email, int $siteId): \App\Framework\Support\Collection
    {
        return Invitation::where('email', $email)
            ->where('site_id', $siteId)
            ->orderBy('id', 'desc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Invitation::class;
    }
}
