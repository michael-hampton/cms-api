<?php

namespace App\Services\Authorization;

interface ContributorRoleAssignmentInterface
{
    public function syncLegacyRole(int $userId, int $siteId, ?string $legacyRole): ?string;
}
