<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\DisputeStatus;
use App\Models\EarningsDispute;
use App\Models\Model;
use App\Repositories\Repository;

class EarningsDisputeRepository extends Repository
{
    public function createForUser(int $userId, int $ledgerId, string $reason): Model
    {
        return $this->create([
            'user_id' => $userId,
            'earnings_ledger_id' => $ledgerId,
            'reason' => $reason,
            'status' => DisputeStatus::Open->value,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markResolved(int $disputeId, string $adminNotes): EarningsDispute
    {
        $this->update($disputeId, [
            'status' => DisputeStatus::Resolved->value,
            'admin_notes' => $adminNotes,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->find($disputeId);
    }

    public function markRejected(int $disputeId, string $adminNotes): EarningsDispute
    {
        $this->update($disputeId, [
            'status' => DisputeStatus::Rejected->value,
            'admin_notes' => $adminNotes,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->find($disputeId);
    }

    /**
     * All open disputes for admin review, oldest first.
     */
    public function openForSite(int $siteId): \App\Framework\Support\Collection
    {
        return EarningsDispute::where('status', DisputeStatus::Open->value)
            ->join('oc_earnings_ledger', 'oc_earnings_ledger.id', '=', 'oc_earnings_disputes.earnings_ledger_id')
            ->where('oc_earnings_ledger.user_id', '>', 0) // scoped join to confirm ledger row exists
            ->orderBy('oc_earnings_disputes.created_at')
            ->select('oc_earnings_disputes.*')
            ->get();
    }

    /**
     * All disputes raised by a specific contributor.
     */
    public function forContributor(int $userId): \App\Framework\Support\Collection
    {
        return EarningsDispute::where('user_id', $userId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Guard: prevent a contributor raising a second open dispute for the same ledger entry.
     */
    public function hasOpenDisputeForLedgerEntry(int $userId, int $ledgerId): bool
    {
        return EarningsDispute::where('user_id', $userId)
            ->where('earnings_ledger_id', $ledgerId)
            ->where('status', DisputeStatus::Open->value)
            ->exists();
    }

    /**
     * Returns true if ANY dispute (open, resolved, or rejected) exists
     * for this user + ledger entry combination.
     * Prevents re-raising a dispute that has already been handled.
     */
    public function hasAnyDisputeForLedgerEntry(int $userId, int $ledgerId): bool
    {
        return EarningsDispute::where('user_id', $userId)
            ->where('earnings_ledger_id', $ledgerId)
            ->exists();
    }

    protected function getModelClass(): string
    {
        return EarningsDispute::class;
    }
}