<?php

namespace App\Services\OpenCollab;

use App\Repositories\OpenCollab\UserSiteRepository;

/**
 * Single authority for user ↔ site access decisions.
 *
 * All access checks must go through canAccessSite(). Do not query
 * user_site directly from controllers or middleware.
 */
class SiteAccessService
{
    public function __construct(
        private readonly UserSiteRepository $userSiteRepository,
    )
    {
    }

    public function canAccessSite(int $userId, int $siteId): bool
    {
        return $this->userSiteRepository->hasAccess($userId, $siteId);
    }

    public function grantAccess(int $userId, int $siteId): void
    {
        $this->userSiteRepository->grant($userId, $siteId);
    }

    public function revokeAccess(int $userId, int $siteId): void
    {
        $this->userSiteRepository->revoke($userId, $siteId);
    }

    /**
     * Returns the IDs of all users who currently have access to this site.
     *
     * @return int[]
     */
    public function getUserIdsForSite(int $siteId): array
    {
        return $this->userSiteRepository->userIdsForSite($siteId);
    }

    /**
     * Grants a user access to every site in the provided list.
     * Used by UserSiteSeeder to back-fill all existing users.
     */
    public function grantAccessToAllSites(int $userId, array $siteIds): void
    {
        foreach ($siteIds as $siteId) {
            $this->userSiteRepository->grant($userId, (int)$siteId);
        }
    }
}