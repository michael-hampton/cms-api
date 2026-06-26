<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\CreatorBalanceService;
use App\Services\OpenCollab\EarningsService;
use App\Services\OpenCollab\PayoutService;
use App\Services\OpenCollab\Surfaces\SurfaceResolver;

class ContributorEarningsPageController extends Controller
{
    public function __construct(
        private readonly EarningsService          $earningsService,
        private readonly CreatorBalanceService    $creatorBalanceService,
        private readonly PayoutService            $payoutService,
        private readonly PayoutRepository         $payoutRepository,
        private readonly ArticlePaymentRepository $paymentRepository,
        private readonly SurfaceResolver          $surfaceResolver,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $userId = Auth::id();
        $siteId = SiteContext::getId();
        $site = SiteContext::slug();

        $balances = $this->creatorBalanceService->balances($userId, $siteId);

        $totalEarnings = $this->earningsService->totalEarningsForContributor($userId);
        $breakdown = $this->earningsService->earningsBreakdownForContributor($userId);

        $availableBalance = $this->payoutService->availableBalance($userId, $siteId);

        $transactionsRaw = $this->paymentRepository->transactionHistoryForContributor($userId, 50);
        $transactions = is_array($transactionsRaw)
            ? ($transactionsRaw['data'] ?? collect([]))
            : $transactionsRaw;

        $payouts = $this->payoutRepository->forContributor($userId, 50);

        return $this->view('open-collab.contributor.earnings.index', [
            'surface' => 'earnings.index',
            'sections' => $this->surfaceResolver->resolve('earnings.index', $site),
            'totalEarnings' => $totalEarnings,
            'availableBalance' => $availableBalance,

            'balances' => $balances,
            'estimatedBalance' => $balances['estimated_balance'] ?? 0,
            'confirmedBalance' => $balances['confirmed_balance'] ?? 0,
            'settledBalance' => $balances['settled_balance'] ?? 0,
            'withdrawnBalance' => $balances['withdrawn_balance'] ?? 0,
            'openLiabilities' => $balances['open_liabilities'] ?? 0,
            'inFlightPayouts' => $balances['in_flight_payouts'] ?? 0,

            'totalPaid' => $balances['withdrawn_balance'] ?? $this->payoutRepository->totalPaidForContributor($userId),
            'totalInFlight' => $balances['in_flight_payouts'] ?? $this->payoutRepository->totalInFlightForContributor($userId),

            'breakdown' => $breakdown,
            'transactions' => $transactions,
            'payouts' => $payouts,
            'pageTitle' => 'Earnings & Payouts',
            'activeNav' => 'earnings',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/contributor/dashboard'],
                ['label' => 'Earnings & Payouts'],
            ],
            'currentUser' => Auth::user(),
            'site' => $site,
        ]);
    }
}
