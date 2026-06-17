<?php

namespace App\Services\Authorization;

interface AuthorisationServiceInterface
{
    public function hasAccess(int $userId, int $siteId, string $capability): bool;

    public function grantAccess(AccessGrantRequest $request): AccessGrantResult;

    public function revokeAccess(AccessRevocationRequest $request): AccessRevocationResult;

    public function assignRole(int $userId, int $siteId, string $role, ?int $actorUserId = null): ?string;

    /**
     * @return int[]
     */
    public function userIdsForSite(int $siteId): array;

    public function hasAnyOtherAccess(int $userId, int $excludingSiteId): bool;
}
