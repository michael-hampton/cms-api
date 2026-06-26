<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Repositories\OpenCollab\EarningsDisputeRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\CreatorBalanceService;
use App\Services\OpenCollab\EarningsService;
use App\Services\OpenCollab\PayoutService;
use App\Services\OpenCollab\Surfaces\SurfaceResolver;

final class SurfaceSectionController extends Controller
{
    public function __construct(
        private readonly SurfaceResolver           $surfaceResolver,
        private readonly EarningsService          $earningsService,
        private readonly CreatorBalanceService    $creatorBalanceService,
        private readonly PayoutService            $payoutService,
        private readonly PayoutRepository         $payoutRepository,
        private readonly ArticlePaymentRepository $paymentRepository,
        private readonly EarningsDisputeRepository $disputeRepository,
        private readonly EarningsLedgerRepository  $ledgerRepository,
    ) {
        parent::__construct();
    }

    public function manifest(string $surface): JsonResponse
    {
        return $this->jsonResponse([
            'surface' => $surface,
            'sections' => $this->surfaceResolver->manifest($surface, SiteContext::slug()),
        ]);
    }

    public function show(string $surface, string $key): JsonResponse
    {
        $sections = $this->surfaceResolver->resolve($surface, SiteContext::slug());
        $section = null;

        foreach ($sections as $candidate) {
            if ($candidate->key() === $key) {
                $section = $candidate;
                break;
            }
        }

        if (!$section) {
            return $this->errorResponse('Surface section not found.', 404);
        }

        return $this->jsonResponse([
            'key' => $section->key(),
            'title' => $section->title(),
            'component' => $section->component(),
            'layout' => $section->layout(),
            'settings' => $section->settings(),
            'data' => $this->dataFor($surface, $key),
        ]);
    }

    private function dataFor(string $surface, string $key): array
    {
        return match ($key) {
            'payouts.stats' => $this->payoutStats(),
            'payouts.history_table' => $this->payoutHistory(),
            'earnings.stats' => $this->earningsStats(),
            'earnings.transactions_table' => $this->earningsFinance(),
            'disputes.stats' => $this->disputeStats(),
            'disputes.table' => $this->disputesTable(),
            default => [],
        };
    }

    private function payoutStats(): array
    {
        $userId = Auth::id();
        $siteId = SiteContext::getId();
        $balances = $this->creatorBalanceService->balances($userId, $siteId);
        $available = $this->payoutService->availableBalance($userId, $siteId);

        return [
            'items' => [
                ['label' => 'Available to Withdraw', 'value' => $available, 'format' => 'money', 'variant' => 'accent', 'sub' => 'Settled minus deductions'],
                ['label' => 'Estimated', 'value' => $balances['estimated_balance'] ?? 0, 'format' => 'money', 'sub' => 'Visible, not payable yet'],
                ['label' => 'Confirmed', 'value' => $balances['confirmed_balance'] ?? 0, 'format' => 'money', 'sub' => 'Approved, not settled'],
                ['label' => 'Withdrawn', 'value' => $balances['withdrawn_balance'] ?? 0, 'format' => 'money', 'variant' => 'green', 'sub' => 'Paid out'],
                ['label' => 'Deductions', 'value' => $balances['open_liabilities'] ?? 0, 'format' => 'money', 'sub' => 'Open liabilities'],
                ['label' => 'Pending Payouts', 'value' => $balances['in_flight_payouts'] ?? 0, 'format' => 'money', 'sub' => 'Pending or approved'],
            ],
            'available_balance' => $available,
            'minimum_payout' => 5000,
        ];
    }

    private function payoutHistory(): array
    {
        $payouts = $this->payoutRepository->forContributor(Auth::id());

        return [
            'items' => $payouts->map(fn($payout) => $this->formatPayout($payout))->toArray(),
            'minimum_payout' => 5000,
        ];
    }

    private function earningsStats(): array
    {
        $userId = Auth::id();
        $siteId = SiteContext::getId();
        $balances = $this->creatorBalanceService->balances($userId, $siteId);
        $totalEarnings = $this->earningsService->totalEarningsForContributor($userId);
        $available = $this->payoutService->availableBalance($userId, $siteId);
        $inFlight = (int)($balances['in_flight_payouts'] ?? $this->payoutRepository->totalInFlightForContributor($userId));

        $items = [
            ['label' => 'Lifetime Earnings', 'value' => $totalEarnings, 'format' => 'money', 'variant' => 'accent', 'sub' => 'Gross revenue all time'],
            ['label' => 'Available Balance', 'value' => $available, 'format' => 'money', 'variant' => 'green', 'sub' => 'Ready to withdraw'],
            ['label' => 'Total Paid Out', 'value' => $balances['withdrawn_balance'] ?? $this->payoutRepository->totalPaidForContributor($userId), 'format' => 'money', 'sub' => 'Received to date'],
        ];

        if ($inFlight > 0) {
            $items[] = ['label' => 'In Progress', 'value' => $inFlight, 'format' => 'money', 'sub' => 'Pending or approved'];
        }

        return ['items' => $items];
    }

    private function earningsFinance(): array
    {
        $userId = Auth::id();
        $transactionsRaw = $this->paymentRepository->transactionHistoryForContributor($userId, 50);
        $transactions = is_array($transactionsRaw) ? ($transactionsRaw['data'] ?? collect([])) : $transactionsRaw;
        $payouts = $this->payoutRepository->forContributor($userId, 50);
        $totalEarnings = $this->earningsService->totalEarningsForContributor($userId);
        $breakdown = $this->earningsService->earningsBreakdownForContributor($userId);

        $txItems = is_object($transactions) && method_exists($transactions, 'map')
            ? $transactions->map(fn($tx) => $this->formatTransaction($tx))->toArray()
            : array_map(fn($tx) => $this->formatTransaction($tx), is_array($transactions) ? $transactions : []);

        return [
            'transactions' => $txItems,
            'payouts' => $payouts->map(fn($payout) => $this->formatPayout($payout))->toArray(),
            'breakdown' => array_map(static function (array $item) use ($totalEarnings): array {
                $itemTotal = (int)($item['total'] ?? 0);
                return [
                    'page_id' => $item['page_id'] ?? null,
                    'title' => $item['title'] ?? 'Untitled',
                    'total' => $itemTotal,
                    'percent' => $totalEarnings > 0 ? min(100, round($itemTotal / $totalEarnings * 100)) : 0,
                ];
            }, is_array($breakdown) ? $breakdown : []),
            'links' => [
                ['label' => 'Request a payout', 'href' => '/contributor/payouts', 'variant' => 'amber'],
                ['label' => 'Earnings disputes', 'href' => '/contributor/disputes', 'variant' => 'ghost'],
                ['label' => 'Payout method settings', 'href' => '/contributor/settings#payment', 'variant' => 'ghost'],
            ],
        ];
    }

    private function disputeStats(): array
    {
        $disputes = $this->disputeRepository->forContributor(Auth::id());

        return [
            'items' => [
                ['label' => 'Open Disputes', 'value' => $disputes->filter(fn($d) => $d->status === 'open')->count(), 'format' => 'number', 'variant' => 'accent', 'sub' => 'Under review'],
                ['label' => 'Resolved', 'value' => $disputes->filter(fn($d) => $d->status === 'resolved')->count(), 'format' => 'number', 'variant' => 'green', 'sub' => 'Closed in your favour'],
                ['label' => 'Rejected', 'value' => $disputes->filter(fn($d) => $d->status === 'rejected')->count(), 'format' => 'number', 'sub' => 'No action taken'],
            ],
        ];
    }

    private function disputesTable(): array
    {
        $userId = Auth::id();
        $disputes = $this->disputeRepository->forContributor($userId);
        $ledgerEntries = $this->ledgerRepository->eligibleForPayout($userId, now_datetime()->subDays(30));
        $openLedgerIds = $disputes->filter(fn($d) => $d->status === 'open')->pluck('earnings_ledger_id')->toArray();

        return [
            'items' => $disputes->map(fn($d) => [
                'id' => $d->id,
                'earnings_ledger_id' => $d->earnings_ledger_id,
                'reason' => $d->reason,
                'status' => $d->status,
                'admin_notes' => $d->admin_notes,
                'created_at' => $d->created_at,
            ])->toArray(),
            'eligible_entries' => $ledgerEntries
                ->filter(fn($entry) => !in_array($entry->id, $openLedgerIds, true))
                ->map(fn($entry) => [
                    'id' => (int)$entry->id,
                    'amount' => (int)$entry->amount,
                    'currency' => strtoupper($entry->currency ?? 'GBP'),
                    'type' => ucfirst($entry->type ?? 'sale'),
                    'earned_at' => $entry->earned_at?->format('d M Y') ?? '',
                ])->values()->toArray(),
        ];
    }

    private function formatTransaction(mixed $tx): array
    {
        $txArr = is_array($tx) ? $tx : (method_exists($tx, 'toArray') ? $tx->toArray() : (array)$tx);

        return [
            'created_at' => $this->dateValue($txArr['created_at'] ?? null),
            'page_title' => $txArr['page_title'] ?? $txArr['title'] ?? '–',
            'status' => $txArr['status'] ?? 'succeeded',
            'amount' => (int)($txArr['amount'] ?? 0),
            'currency' => strtoupper($txArr['currency'] ?? 'GBP'),
        ];
    }

    private function formatPayout(mixed $payout): array
    {
        return [
            'id' => $payout->id,
            'amount' => (int)$payout->amount,
            'amount_pence' => (int)$payout->amount,
            'currency' => strtoupper($payout->currency ?? 'GBP'),
            'status' => $payout->status ?? 'pending',
            'method' => $payout->method ?? null,
            'reference' => $payout->reference ?? null,
            'rejection_reason' => $payout->rejection_reason ?? null,
            'created_at' => $this->dateValue($payout->created_at ?? null),
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'format')) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string)$value;
    }
}
