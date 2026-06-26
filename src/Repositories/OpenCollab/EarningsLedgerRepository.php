<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Enums\OpenCollab\LedgerEntryType;
use App\Exceptions\OpenCollab\InvalidAccrualTransitionException;
use App\Framework\Support\Collection;
use App\Models\EarningsLedger;
use App\Models\Model;
use App\Repositories\Repository;

class EarningsLedgerRepository extends Repository
{
    public function recordSale(
        int    $userId,
        int    $articleId,
        int    $amount,
        string $currency,
        string $referenceId,
    ): Model
    {
        return $this->create([
            'user_id' => $userId,
            'article_id' => $articleId,
            'type' => LedgerEntryType::Sale->value,
            'amount' => $amount,
            'currency' => $currency,
            'reference_id' => $referenceId,
            'earned_at' => now()
        ]);
    }

    public function recordRefund(
        int    $userId,
        int    $articleId,
        int    $amount,
        string $currency,
        string $referenceId,
    ): Model
    {
        return $this->create([
            'user_id' => $userId,
            'article_id' => $articleId,
            'type' => LedgerEntryType::Refund->value,
            'amount' => -abs($amount),
            'currency' => $currency,
            'reference_id' => $referenceId,
            'earned_at' => now()
        ]);
    }

    public function balanceForContributor(int $userId): int
    {
        return (int) EarningsLedger::where('user_id', $userId)->sum('amount');
    }

    public function totalEarningsForContributor(int $userId, ?int $siteId = null): int
    {
        $table = (new EarningsLedger())->getTable();

        $query = EarningsLedger::query()
            ->where("{$table}.user_id", $userId)
            ->where("{$table}.accrual_status", '!=', AccrualStatus::Reversed->value);

        if ($siteId !== null) {
            $query
                ->join('pages', 'pages.id', '=', "{$table}.article_id")
                ->where('pages.site_id', $siteId);
        }

        return (int) $query->sum("{$table}.amount");
    }

    /**
     * Revenue grouped by article using the earnings ledger source of truth.
     *
     * @return array<int, array{page_id: int, title: string, total: int, percent: float}>
     */
    public function earningsBreakdownForContributor(int $userId, ?int $siteId = null): array
    {
        $table = (new EarningsLedger())->getTable();

        $query = EarningsLedger::query()
            ->join('pages', 'pages.id', '=', "{$table}.article_id")
            ->where("{$table}.user_id", $userId)
            ->where("{$table}.accrual_status", '!=', AccrualStatus::Reversed->value);

        if ($siteId !== null) {
            $query->where('pages.site_id', $siteId);
        }

        $rows = $query
            ->selectRaw("{$table}.article_id as page_id, pages.title, COALESCE(SUM({$table}.amount), 0) as total")
            ->groupBy("{$table}.article_id", 'pages.title')
            ->orderByDesc('total')
            ->get();

        $items = $rows
            ->map(function ($row) {
                return [
                    'page_id' => (int) $this->rowValue($row, 'page_id', 0),
                    'title' => (string) $this->rowValue($row, 'title', 'Untitled'),
                    'total' => (int) $this->rowValue($row, 'total', 0),
                ];
            })
            ->values()
            ->toArray();

        $max = max(array_map(fn($item) => abs((int) $item['total']), $items) ?: [0]);

        return array_map(function ($item) use ($max) {
            $item['percent'] = $max > 0 ? round((abs((int) $item['total']) / $max) * 100, 2) : 0;

            return $item;
        }, $items);
    }

    /**
     * Ledger-backed transaction history for the contributor earnings page.
     *
     * This is the source of truth for contributor earnings because balances and
     * payout eligibility are also calculated from oc_earnings_ledger.
     *
     * @return array<int, array{page_title: string, amount: int, currency: string, status: string, accrual_status: string, type: string, reference_id: string|null, created_at: mixed}>
     */
    public function transactionHistoryForContributor(int $userId, ?int $siteId = null, int $limit = 50): array
    {
        $table = (new EarningsLedger())->getTable();

        $query = EarningsLedger::query()
            ->leftJoin('pages', 'pages.id', '=', "{$table}.article_id")
            ->where("{$table}.user_id", $userId);

        if ($siteId !== null) {
            $query->where('pages.site_id', $siteId);
        }

        return $query
            ->select(
                "{$table}.id",
                "{$table}.type",
                "{$table}.amount",
                "{$table}.currency",
                "{$table}.reference_id",
                "{$table}.accrual_status",
                "{$table}.earned_at",
                'pages.title as page_title',
            )
            ->orderByDesc("{$table}.earned_at")
            ->orderByDesc("{$table}.id")
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $amount = (int) $this->rowValue($row, 'amount', 0);
                $type = (string) $this->rowValue($row, 'type', LedgerEntryType::Sale->value);

                return [
                    'page_title' => (string) $this->rowValue($row, 'page_title', '–'),
                    'amount' => abs($amount),
                    'currency' => strtoupper((string) $this->rowValue($row, 'currency', 'GBP')),
                    'status' => $type === LedgerEntryType::Refund->value ? 'refunded' : 'succeeded',
                    'accrual_status' => (string) $this->rowValue($row, 'accrual_status', AccrualStatus::Estimated->value),
                    'type' => $type,
                    'reference_id' => $this->rowValue($row, 'reference_id'),
                    'created_at' => $this->rowValue($row, 'earned_at'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Ledger entries for a contributor that were created before $cutoff
     * and have not yet been marked as paid.
     */
    public function eligibleForPayout(int $userId, \DateTime $cutoff): Collection
    {
        return EarningsLedger::query()
            ->where('user_id', $userId)
            ->where('earned_at', '<=', $cutoff->format('Y-m-d H:i'))
            ->whereNull('paid_at')
            ->orderBy('earned_at')
            ->get();
    }

    /**
     * Eligible entries for ALL contributors on a site, grouped by user_id.
     * Returns: array<int (userId), array<int, array{amount: int, currency: string}>>
     * Used exclusively by PayoutSchedulerService.
     */
    public function eligibleGroupedBySiteAndUser(int $siteId, \DateTime $cutoff): array
    {
        $table = (new EarningsLedger())->getTable();

        $rows = EarningsLedger::query()
            ->join('pages', 'pages.id', '=', "{$table}.article_id")
            ->where('pages.site_id', $siteId)
            ->where("{$table}.earned_at", '<=', $cutoff->format('Y-m-d H:i:s'))
            ->whereNull("{$table}.paid_at")
            ->select(
                "{$table}.user_id",
                "{$table}.amount",
                "{$table}.currency",
            )
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $userId = (int) $this->rowValue($row, 'user_id');
            $grouped[$userId][] = [
                'amount' => (int) $this->rowValue($row, 'amount', 0),
                'currency' => $this->rowValue($row, 'currency'),
            ];
        }

        return $grouped;
    }

    /**
     * Sum of eligible (un-paid, past delay) earnings for a contributor.
     */
    public function eligibleBalanceForContributor(int $userId, \DateTime $cutoff): int
    {
        return (int) EarningsLedger::where('user_id', $userId)
            ->where('earned_at', '<=', $cutoff->format('Y-m-d H:i:s'))
            ->whereNull('paid_at')
            ->sum('amount');
    }

    /**
     * Validate and apply an accrual status transition.
     *
     * @throws InvalidAccrualTransitionException if the transition is not permitted
     */
    public function transition(
        int           $ledgerEntryId,
        AccrualStatus $to,
        ?string       $timestampColumn = null,
        array         $extra           = [],
    ): EarningsLedger {
        $entry = $this->findOrFail($ledgerEntryId);

        $from = AccrualStatus::from($entry->accrual_status);

        if (!$from->canTransitionTo($to)) {
            throw new InvalidAccrualTransitionException($ledgerEntryId, $from, $to);
        }

        $updates = array_merge(['accrual_status' => $to->value], $extra);

        if ($timestampColumn !== null) {
            $updates[$timestampColumn] = now_datetime()->format('Y-m-d H:i:s');
        }

        $this->update($ledgerEntryId, $updates);

        return $this->find($ledgerEntryId);
    }

    /**
     * Confirm an estimated or confirmed entry.
     *
     * @throws InvalidAccrualTransitionException
     */
    public function confirm(int $ledgerEntryId, ?int $actorId = null): EarningsLedger
    {
        return $this->transition(
            $ledgerEntryId,
            AccrualStatus::Confirmed,
            'confirmed_at',
            $actorId ? ['confirmed_by' => $actorId] : [],
        );
    }

    /**
     * Settle a confirmed entry (make it withdrawable).
     *
     * @throws InvalidAccrualTransitionException
     */
    public function settle(int $ledgerEntryId, ?int $actorId = null): EarningsLedger
    {
        return $this->transition(
            $ledgerEntryId,
            AccrualStatus::Settled,
            'settled_at',
            $actorId ? ['settled_by' => $actorId] : [],
        );
    }

    /**
     * Withdraw a settled entry (include it in a payout).
     *
     * @throws InvalidAccrualTransitionException
     */
    public function withdraw(int $ledgerEntryId, int $payoutId): EarningsLedger
    {
        return $this->transition(
            $ledgerEntryId,
            AccrualStatus::Withdrawn,
            'withdrawn_at',
            ['payout_id' => $payoutId],
        );
    }

    /**
     * Reverse an entry (estimated, confirmed, or settled only).
     *
     * Withdrawn entries CANNOT be reversed directly — use the liability engine.
     *
     * @throws InvalidAccrualTransitionException
     */
    public function reverse(int $ledgerEntryId, string $reason, ?int $actorId = null): EarningsLedger
    {
        return $this->transition(
            $ledgerEntryId,
            AccrualStatus::Reversed,
            'reversed_at',
            array_filter([
                'reversal_reason' => $reason,
                'reversed_by'     => $actorId,
            ]),
        );
    }

    /**
     * Returns the current AccrualStatus for a ledger entry.
     *
     * @throws \InvalidArgumentException if the entry is not found
     */
    public function currentStatus(int $ledgerEntryId): AccrualStatus
    {
        $entry = $this->findOrFail($ledgerEntryId);

        return AccrualStatus::from($entry->accrual_status);
    }

    /**
     * Returns all settled entries for a contributor that are not yet withdrawn.
     * These are the entries that contribute to the withdrawable balance.
     */
    public function settledForContributor(int $userId): \App\Framework\Support\Collection
    {
        return EarningsLedger::where('user_id', $userId)
            ->where('accrual_status', AccrualStatus::Settled->value)
            ->orderBy('earned_at')
            ->get();
    }

    /**
     * Sum of settled (withdrawable) earnings for a contributor in pence.
     */
    public function settledBalanceForContributor(int $userId): int
    {
        return (int) EarningsLedger::where('user_id', $userId)
            ->where('accrual_status', AccrualStatus::Settled->value)
            ->sum('amount');
    }

    /**
     * Aggregate balances grouped by accrual_status for a contributor.
     *
     * Returns a map: ['estimated' => int, 'confirmed' => int, 'settled' => int, ...]
     *
     * @return array<string, int>
     */
    public function balancesByStatusForContributor(int $userId, ?int $siteId = null): array
    {
        $table = (new EarningsLedger())->getTable();

        $query = EarningsLedger::query()
            ->where("{$table}.user_id", $userId);

        if ($siteId !== null) {
            $query
                ->join('pages', 'pages.id', '=', "{$table}.article_id")
                ->where('pages.site_id', $siteId);
        }

        $rows = $query
            ->selectRaw("{$table}.accrual_status, COALESCE(SUM({$table}.amount), 0) as total")
            ->groupBy("{$table}.accrual_status")
            ->get();

        $balances = [];
        foreach (AccrualStatus::cases() as $status) {
            $balances[$status->value] = 0;
        }

        foreach ($rows as $row) {
            $balances[$this->rowValue($row, 'accrual_status')] = (int) $this->rowValue($row, 'total', 0);
        }

        return $balances;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function findOrFail(int $ledgerEntryId): EarningsLedger
    {
        $entry = $this->find($ledgerEntryId);

        if (!$entry) {
            throw new \InvalidArgumentException(
                "Earnings ledger entry [{$ledgerEntryId}] not found."
            );
        }

        return $entry;
    }

    public function settledAvailableForPayout(int $userId, ?int $siteId = null): \App\Framework\Support\Collection
    {
        $table = (new EarningsLedger())->getTable();

        $query = EarningsLedger::query()
            ->where("{$table}.user_id", $userId)
            ->where("{$table}.accrual_status", AccrualStatus::Settled->value)
            ->whereNull("{$table}.payout_id");

        if ($siteId !== null) {
            $query
                ->join('pages', 'pages.id', '=', "{$table}.article_id")
                ->where('pages.site_id', $siteId)
                ->select("{$table}.*");
        }

        return $query
            ->orderBy("{$table}.earned_at")
            ->orderBy("{$table}.id")
            ->get();
    }

    public function updateAccrualStatus(
        int $ledgerEntryId,
        AccrualStatus $status,
        array $metadata = [],
    ): EarningsLedger {
        $this->update($ledgerEntryId, array_merge([
            'accrual_status' => $status->value,
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ], $metadata));

        $entry = $this->find($ledgerEntryId);

        if (!$entry instanceof EarningsLedger) {
            return EarningsLedger::hydrateStatic($entry->toArray());
        }

        return $entry;
    }

    private function rowValue(mixed $row, string $key, mixed $default = null): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? $default;
        }

        if (is_object($row)) {
            if (isset($row->{$key})) {
                return $row->{$key};
            }

            if (method_exists($row, 'toArray')) {
                $data = $row->toArray();

                return $data[$key] ?? $default;
            }
        }

        return $default;
    }

    protected function getModelClass(): string
    {
        return EarningsLedger::class;
    }
}
