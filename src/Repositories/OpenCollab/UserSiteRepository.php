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

    protected function getModelClass(): string
    {
        return UserSite::class;
    }
}