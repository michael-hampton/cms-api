<?php

namespace App\Repositories\OpenCollab;

use App\Models\ContributorRequest;
use App\Repositories\Repository;

class ContributorRequestRepository extends Repository
{

    /**
     * Returns true if a pending (unreviewed) request already exists
     * for this email on this site. Used as a duplicate guard.
     */
    public function hasPendingRequest(string $email, int $siteId): bool
    {
        return ContributorRequest::where('email', $email)
            ->where('site_id', $siteId)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * All pending requests for a site, oldest first (FIFO review queue).
     */
    public function pendingForSite(int $siteId): \App\Framework\Support\Collection
    {
        return ContributorRequest::where('site_id', $siteId)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * All requests for a site regardless of status, newest first.
     */
    public function allForSite(int $siteId): \App\Framework\Support\Collection
    {
        return ContributorRequest::where('site_id', $siteId)
            ->orderByDesc('created_at')
            ->get();
    }

    protected function getModelClass(): string
    {
        return ContributorRequest::class;
    }
}