<?php

namespace App\Services\Authorization;

interface UserSiteAccessStoreInterface
{
    public function grant(int $userId, int $siteId): void;

    public function hasAccess(int $userId, int $siteId): bool;

    public function revoke(int $userId, int $siteId): void;

    /**
     * @return int[]
     */
    public function userIdsForSite(int $siteId): array;

    public function hasAnyOtherAccess(int $userId, int $excludingSiteId): bool;
}
