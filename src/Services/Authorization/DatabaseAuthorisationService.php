<?php

namespace App\Services\Authorization;

use App\Services\OpenCollab\PermissionCacheInvalidator;

final class DatabaseAuthorisationService implements AuthorisationServiceInterface
{
    public function __construct(
        private readonly UserSiteAccessStoreInterface $userSites,
        private readonly ContributorRoleAssignmentInterface $siteRoles,
        private ?PermissionCacheInvalidator $permissionCacheInvalidator = null,
    ) {
    }

    public function hasAccess(int $userId, int $siteId, string $capability): bool
    {
        if ($capability !== AccessCapability::OPEN_COLLAB_CONTRIBUTOR) {
            return false;
        }

        return $this->userSites->hasAccess($userId, $siteId);
    }

    public function grantAccess(AccessGrantRequest $request): AccessGrantResult
    {
        $this->assertContributorCapability($request->capability);

        $this->userSites->grant($request->userId, $request->siteId);
        $this->permissionCacheInvalidator()?->invalidateUser($request->userId);

        return new AccessGrantResult(true);
    }

    public function revokeAccess(AccessRevocationRequest $request): AccessRevocationResult
    {
        $this->assertContributorCapability($request->capability);

        $this->userSites->revoke($request->userId, $request->siteId);
        $this->permissionCacheInvalidator()?->invalidateUser($request->userId);

        return new AccessRevocationResult(true);
    }

    public function assignRole(int $userId, int $siteId, string $role, ?int $actorUserId = null): ?string
    {
        return $this->siteRoles->syncLegacyRole($userId, $siteId, $role);
    }

    public function userIdsForSite(int $siteId): array
    {
        return $this->userSites->userIdsForSite($siteId);
    }

    public function hasAnyOtherAccess(int $userId, int $excludingSiteId): bool
    {
        return $this->userSites->hasAnyOtherAccess($userId, $excludingSiteId);
    }

    private function assertContributorCapability(string $capability): void
    {
        if ($capability !== AccessCapability::OPEN_COLLAB_CONTRIBUTOR) {
            throw new \InvalidArgumentException('Unsupported access capability.');
        }
    }

    private function permissionCacheInvalidator(): ?PermissionCacheInvalidator
    {
        if ($this->permissionCacheInvalidator) {
            return $this->permissionCacheInvalidator;
        }

        try {
            return $this->permissionCacheInvalidator = app(PermissionCacheInvalidator::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
