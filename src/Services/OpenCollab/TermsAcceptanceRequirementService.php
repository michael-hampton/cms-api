<?php

namespace App\Services\OpenCollab;

use App\Models\TermsVersion;
use App\Repositories\OpenCollab\TermsVersionRepository;

class TermsAcceptanceRequirementService
{
    public function __construct(private readonly TermsVersionRepository $repository)
    {
    }

    public function currentVisibleVersion(int $siteId): ?TermsVersion
    {
        return $this->repository->latestPublishedForSite($siteId);
    }

    public function currentRequiredVersion(int $siteId): ?TermsVersion
    {
        return $this->repository->latestMaterialPublishedForSite($siteId)
            ?? $this->repository->latestPublishedForSite($siteId);
    }

    public function requiresAcceptance(int $userId, int $siteId): bool
    {
        $required = $this->currentRequiredVersion($siteId);

        return $required !== null
            && !$this->repository->hasAccepted($userId, $siteId, (int)$required->id);
    }
}
