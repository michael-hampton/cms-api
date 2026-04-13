<?php

namespace App\Repositories\OpenCollab;

use App\Models\UserSite;
use App\Repositories\Repository;

class UserSiteRepository extends Repository
{
    public function grant(int $userId, int $siteId): void
    {
        if (!$this->hasAccess($userId, $siteId)) {
            $this->create(['user_id' => $userId, 'site_id' => $siteId]);
        }
    }

    public function hasAccess(int $userId, int $siteId): bool
    {
        return UserSite::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->exists();
    }

    public function revoke(int $userId, int $siteId): void
    {
        UserSite::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->delete();
    }

    /**
     * Returns all site IDs a user has access to.
     */
    public function siteIdsForUser(int $userId): array
    {
        return UserSite::where('user_id', $userId)
            ->get()
            ->pluck('site_id')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }

    /**
     * Returns true if the user has active site access on ANY site other
     * than $excludingSiteId.
     * Used by ContributorTerminationService to decide whether to deactivate
     * the user account globally — we must not lock them out of other sites.
     */
    public function hasAnyOtherAccess(int $userId, int $excludingSiteId): bool
    {
        return UserSite::where('user_id', $userId)
            ->where('site_id', '!=', $excludingSiteId)
            ->exists();
    }

    protected function getModelClass(): string
    {
        return UserSite::class;
    }
}