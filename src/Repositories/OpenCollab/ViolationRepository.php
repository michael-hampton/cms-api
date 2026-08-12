<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\ViolationAction;
use App\Enums\OpenCollab\ViolationSeverity;
use App\Models\ContributorViolation;
use App\Repositories\Repository;

class ViolationRepository extends Repository
{
    /**
     * All violations for a contributor on a site, newest first.
     */
    public function forContributor(int $userId, int $siteId): \App\Framework\Support\Collection
    {
        return ContributorViolation::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Unresolved violations for a contributor on a site.
     */
    public function unresolvedForContributor(int $userId, int $siteId): \App\Framework\Support\Collection
    {
        return ContributorViolation::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Count of unresolved violations by severity for a contributor.
     * Used by threshold enforcement.
     */
    public function unresolvedCountBySeverity(int $userId, int $siteId, ViolationSeverity $severity): int
    {
        return (int)ContributorViolation::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->where('severity', $severity->value)
            ->whereNull('resolved_at')
            ->count();
    }

    /**
     * Returns true if the contributor has any active (unresolved) ban.
     */
    public function hasActiveBan(int $userId, int $siteId): bool
    {
        return ContributorViolation::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->where('action_taken', ViolationAction::Ban->value)
            ->whereNull('resolved_at')
            ->exists();
    }

    /**
     * Returns true if the contributor has any active (unresolved) ban on any site.
     */
    public function hasActiveBanForUser(int $userId): bool
    {
        return ContributorViolation::where('user_id', $userId)
            ->where('action_taken', ViolationAction::Ban->value)
            ->whereNull('resolved_at')
            ->exists();
    }

    /**
     * Returns true if the contributor has any active (unresolved) suspension.
     */
    public function hasActiveSuspension(int $userId, int $siteId): bool
    {
        return ContributorViolation::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->where('action_taken', ViolationAction::Suspension->value)
            ->whereNull('resolved_at')
            ->exists();
    }

    /**
     * Returns true if the contributor has any active (unresolved) suspension on any site.
     */
    public function hasActiveSuspensionForUser(int $userId): bool
    {
        return ContributorViolation::where('user_id', $userId)
            ->where('action_taken', ViolationAction::Suspension->value)
            ->whereNull('resolved_at')
            ->exists();
    }

    /**
     * All violations across all contributors for a site — admin list view.
     */
    public function forSite(int $siteId, int $perPage = 25): array
    {
        return ContributorViolation::where('site_id', $siteId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    protected function getModelClass(): string
    {
        return ContributorViolation::class;
    }
}