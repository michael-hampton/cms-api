<?php

namespace App\Repositories\OpenCollab;

use App\Models\Invitation;
use App\Repositories\Repository;

class InvitationRepository extends Repository
{
    /**
     * Returns true if a live (pending, non-expired, non-revoked) invitation
     * exists for the given email on the given site.
     *
     * Previous implementation ignored $siteId and returned a model instance,
     * making the truthy check wrong for expired/used records.
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

    public function markAsUsed(int $id): void
    {
        Invitation::where('id', $id)->update([
            'used_at' => date('Y-m-d H:i:s'),
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
     * Returns all invitations for a site, ordered newest first.
     * Used by the admin invitation list.
     */
    public function getAllForSite(int $siteId): \App\Framework\Support\Collection
    {
        return Invitation::where('site_id', $siteId)
            ->orderBy('id', 'desc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Invitation::class;
    }
}