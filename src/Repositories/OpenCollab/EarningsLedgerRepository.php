<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\LedgerEntryType;
use App\Framework\Support\Collection;
use App\Models\EarningsLedger;
use App\Repositories\Repository;

class EarningsLedgerRepository extends Repository
{
    public function recordSale(
        int    $userId,
        int    $articleId,
        int    $amount,
        string $currency,
        string $referenceId,
    ): EarningsLedger
    {
        return $this->create([
            'user_id' => $userId,
            'article_id' => $articleId,
            'type' => LedgerEntryType::Sale->value,
            'amount' => $amount,
            'currency' => $currency,
            'reference_id' => $referenceId,
        ]);
    }

    public function recordRefund(
        int    $userId,
        int    $articleId,
        int    $amount,
        string $currency,
        string $referenceId,
    ): EarningsLedger
    {
        return $this->create([
            'user_id' => $userId,
            'article_id' => $articleId,
            'type' => LedgerEntryType::Refund->value,
            'amount' => -abs($amount),
            'currency' => $currency,
            'reference_id' => $referenceId,
        ]);
    }

    public function balanceForContributor(int $userId): int
    {
        return (int)EarningsLedger::where('user_id', $userId)->sum('amount');
    }

    /**
     * Ledger entries for a contributor that were created before $cutoff
     * and have not yet been marked as paid.
     */
    public function eligibleForPayout(int $userId, \DateTime $cutoff): Collection
    {
        return EarningsLedger::where('user_id', $userId)
            ->where('created_at', '<=', $cutoff->format('Y-m-d H:i:s'))
            ->whereNull('paid_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Eligible entries for ALL contributors on a site, grouped by user_id.
     * Returns: array<int (userId), array<int, array{amount: int, currency: string}>>
     * Used exclusively by PayoutSchedulerService.
     */
    public function eligibleGroupedBySiteAndUser(int $siteId, \DateTime $cutoff): array
    {
        $rows = EarningsLedger::join('pages', 'pages.id', '=', 'earnings_ledger.article_id')
            ->where('pages.site_id', $siteId)
            ->where('earnings_ledger.created_at', '<=', $cutoff->format('Y-m-d H:i:s'))
            ->whereNull('earnings_ledger.paid_at')
            ->select(
                'earnings_ledger.user_id',
                'earnings_ledger.amount',
                'earnings_ledger.currency',
            )
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $userId = (int)$row->user_id;
            $grouped[$userId][] = [
                'amount' => (int)$row->amount,
                'currency' => $row->currency,
            ];
        }

        return $grouped;
    }

    /**
     * Sum of eligible (un-paid, past delay) earnings for a contributor.
     */
    public function eligibleBalanceForContributor(int $userId, \DateTime $cutoff): int
    {
        return (int)EarningsLedger::where('user_id', $userId)
            ->where('created_at', '<=', $cutoff->format('Y-m-d H:i:s'))
            ->whereNull('paid_at')
            ->sum('amount');
    }

    protected function getModelClass(): string
    {
        return EarningsLedger::class;
    }
}