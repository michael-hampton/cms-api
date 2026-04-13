<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Framework\Support\Collection;
use App\Models\Payout;
use App\Repositories\Repository;

class PayoutRepository extends Repository
{
    /**
     * All payouts for a contributor, newest first.
     */
    public function forContributor(int $userId, int $limit = 20): \App\Framework\Support\Collection
    {
        return Payout::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * All payouts for a site, newest first — used by the admin panel.
     */
    public function forSite(int $siteId, int $perPage = 25): array
    {
        return Payout::where('site_id', $siteId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Pending payouts for a site — the admin review queue.
     */
    public function pendingForSite(int $siteId): \App\Framework\Support\Collection
    {
        return Payout::where('site_id', $siteId)
            ->where('status', PayoutStatus::Pending->value)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Total amount already paid out to a contributor (completed payouts only).
     */
    public function totalPaidForContributor(int $userId): int
    {
        return (int)Payout::where('user_id', $userId)
            ->where('status', PayoutStatus::Paid->value)
            ->sum('amount');
    }

    /**
     * Total amount in-flight (pending + approved) for a contributor.
     * Used to prevent double-requesting.
     */
    public function totalInFlightForContributor(int $userId): int
    {
        return (int)Payout::where('user_id', $userId)
            ->whereIn('status', [
                PayoutStatus::Pending->value,
                PayoutStatus::Approved->value,
            ])
            ->sum('amount');
    }

    /**
     * Returns true if a pending or approved payout exists for this
     * contributor scoped to a specific currency.
     * Used by the scheduler to allow concurrent payouts in different currencies.
     */
    public function hasInFlightForContributorAndCurrency(int $userId, string $currency): bool
    {
        return Payout::where('user_id', $userId)
            ->whereIn('status', [
                PayoutStatus::Pending->value,
                PayoutStatus::Approved->value,
            ])
            ->where('currency', strtoupper($currency))
            ->exists();
    }

    /**
     * Returns all pending or approved payouts for a contributor.
     * Filtering is done in the DB — do not load all payouts and filter in PHP.
     * Used by ContributorTerminationService to cancel in-flight payouts on closure.
     *
     * @return Collection<Payout>
     */
    public function inFlightForContributor(int $userId): Collection
    {
        return Payout::where('user_id', $userId)
            ->whereIn('status', [
                PayoutStatus::Pending->value,
                PayoutStatus::Approved->value,
            ])
            ->get();
    }

    protected function getModelClass(): string
    {
        return Payout::class;
    }
}