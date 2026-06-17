<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ContributorAccessGrantRequest;
use App\DTO\OpenCollab\ContributorAccessRevocationRequest;

/**
 * OpenCollab-facing adapter for user/site access decisions.
 *
 * The central authorisation layer owns the persisted access state. This class
 * remains as a compatibility facade for existing OpenCollab callers.
 */
class SiteAccessService
{
    public function __construct(
        private readonly OpenCollabAuthorisationInterface $authorisation,
    )
    {
    }

    public function canAccessSite(int $userId, int $siteId): bool
    {
        return $this->authorisation->hasContributorAccess($userId, $siteId);
    }

    public function grantAccess(int $userId, int $siteId): void
    {
        $this->authorisation->grantContributorAccess(new ContributorAccessGrantRequest(
            userId: $userId,
            siteId: $siteId,
        ));
    }

    public function revokeAccess(int $userId, int $siteId): void
    {
        $this->authorisation->revokeContributorAccess(new ContributorAccessRevocationRequest(
            userId: $userId,
            siteId: $siteId,
        ));
    }

    /**
     * Returns the IDs of all users who currently have access to this site.
     *
     * @return int[]
     */
    public function getUserIdsForSite(int $siteId): array
    {
        return $this->authorisation->contributorUserIdsForSite($siteId);
    }

    /**
     * Grants a user access to every site in the provided list.
     * Used by UserSiteSeeder to back-fill all existing users.
     */
    public function grantAccessToAllSites(int $userId, array $siteIds): void
    {
        foreach ($siteIds as $siteId) {
            $this->grantAccess($userId, (int) $siteId);
        }
    }
}
