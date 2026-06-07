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

    public function createWithIdempotency(array $data): Payout
    {
        if (empty($data['idempotency_key'])) {
            throw new \InvalidArgumentException('Payout idempotency key is required.');
        }

        return $this->create($data);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Payout
    {
        return Payout::where('idempotency_key', $idempotencyKey)->first();
    }

    public function existsForWindowAndBatch(
        int $userId,
        int $siteId,
        ?int $accrualWindowId,
        ?int $batchId,
    ): bool {
        if ($accrualWindowId === null || $batchId === null) {
            return false;
        }

        return Payout::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->where('accrual_window_id', $accrualWindowId)
            ->where('batch_id', $batchId)
            ->exists();
    }

    public function statusesForBatch(int $batchId): array
    {
        $rows = Payout::where('batch_id', $batchId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $statuses = [];

        foreach ($rows as $row) {
            $statuses[$row->status] = (int) $row->total;
        }

        return $statuses;
    }

    public function incrementProcessingAttempts(int $payoutId): void
    {
        $payout = $this->find($payoutId);

        if (!$payout) {
            throw new \InvalidArgumentException("Payout [{$payoutId}] not found.");
        }

        $this->update($payoutId, [
            'processing_attempts' => ((int) ($payout->processing_attempts ?? 0)) + 1,
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    protected function getModelClass(): string
    {
        return Payout::class;
    }
}