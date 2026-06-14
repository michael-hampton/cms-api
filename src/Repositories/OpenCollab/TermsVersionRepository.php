<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\TermsVersionStatus;
use App\Framework\Support\Collection;
use App\Models\TermsVersion;
use App\Models\UserTermsAcceptance;
use App\Repositories\Repository;

class TermsVersionRepository extends Repository
{
    public function allForSite(int $siteId): Collection
    {
        return TermsVersion::where('site_id', $siteId)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    public function latestPublishedForSite(int $siteId): ?TermsVersion
    {
        return TermsVersion::where('site_id', $siteId)
            ->where('status', TermsVersionStatus::Published->value)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    public function latestMaterialPublishedForSite(int $siteId): ?TermsVersion
    {
        return TermsVersion::where('site_id', $siteId)
            ->where('status', TermsVersionStatus::Published->value)
            ->where('is_material_change', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    public function findForSite(int $id, int $siteId): ?TermsVersion
    {
        return TermsVersion::where('id', $id)
            ->where('site_id', $siteId)
            ->first();
    }

    public function hasAccepted(int $userId, int $siteId, int $termsVersionId): bool
    {
        return UserTermsAcceptance::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->where('terms_version_id', $termsVersionId)
            ->exists();
    }

    public function recordAcceptance(array $attributes): UserTermsAcceptance
    {
        $existing = UserTermsAcceptance::where('user_id', $attributes['user_id'])
            ->where('site_id', $attributes['site_id'])
            ->where('terms_version_id', $attributes['terms_version_id'])
            ->first();

        return $existing ?: UserTermsAcceptance::create($attributes);
    }

    protected function getModelClass(): string
    {
        return TermsVersion::class;
    }
}
