<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ContributorAccessGrantRequest;
use App\DTO\OpenCollab\ContributorAccessRevocationRequest;
use App\DTO\OpenCollab\ContributorRoleAssignmentRequest;
use App\Services\Authorization\AccessGrantResult;
use App\Services\Authorization\AccessRevocationResult;

interface OpenCollabAuthorisationInterface
{
    public function hasContributorAccess(int $userId, int $siteId): bool;

    public function grantContributorAccess(ContributorAccessGrantRequest $request): AccessGrantResult;

    public function revokeContributorAccess(ContributorAccessRevocationRequest $request): AccessRevocationResult;

    public function assignContributorRole(ContributorRoleAssignmentRequest $request): ?string;

    /**
     * @return int[]
     */
    public function contributorUserIdsForSite(int $siteId): array;

    public function hasOtherContributorAccess(int $userId, int $excludingSiteId): bool;
}
