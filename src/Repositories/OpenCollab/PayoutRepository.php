<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
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

    protected function getModelClass(): string
    {
        return Payout::class;
    }
}