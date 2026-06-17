<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ContributorAccessGrantRequest;
use App\DTO\OpenCollab\ContributorAccessRevocationRequest;
use App\DTO\OpenCollab\ContributorRoleAssignmentRequest;
use App\Services\Authorization\AccessCapability;
use App\Services\Authorization\AccessGrantRequest;
use App\Services\Authorization\AccessGrantResult;
use App\Services\Authorization\AccessRevocationRequest;
use App\Services\Authorization\AccessRevocationResult;
use App\Services\Authorization\AuthorisationServiceInterface;

final readonly class OpenCollabAuthorisation implements OpenCollabAuthorisationInterface
{
    public function __construct(
        private AuthorisationServiceInterface $authorisation,
    ) {
    }

    public function hasContributorAccess(int $userId, int $siteId): bool
    {
        return $this->authorisation->hasAccess(
            $userId,
            $siteId,
            AccessCapability::OPEN_COLLAB_CONTRIBUTOR,
        );
    }

    public function grantContributorAccess(ContributorAccessGrantRequest $request): AccessGrantResult
    {
        return $this->authorisation->grantAccess(new AccessGrantRequest(
            userId: $request->userId,
            siteId: $request->siteId,
            capability: AccessCapability::OPEN_COLLAB_CONTRIBUTOR,
            actorUserId: $request->actorUserId,
            invitationId: $request->invitationId,
            reason: $request->reason,
        ));
    }

    public function revokeContributorAccess(ContributorAccessRevocationRequest $request): AccessRevocationResult
    {
        return $this->authorisation->revokeAccess(new AccessRevocationRequest(
            userId: $request->userId,
            siteId: $request->siteId,
            capability: AccessCapability::OPEN_COLLAB_CONTRIBUTOR,
            actorUserId: $request->actorUserId,
            reason: $request->reason,
        ));
    }

    public function assignContributorRole(ContributorRoleAssignmentRequest $request): ?string
    {
        return $this->authorisation->assignRole(
            $request->userId,
            $request->siteId,
            $request->role,
            $request->actorUserId,
        );
    }

    public function contributorUserIdsForSite(int $siteId): array
    {
        return $this->authorisation->userIdsForSite($siteId);
    }

    public function hasOtherContributorAccess(int $userId, int $excludingSiteId): bool
    {
        return $this->authorisation->hasAnyOtherAccess($userId, $excludingSiteId);
    }
}
