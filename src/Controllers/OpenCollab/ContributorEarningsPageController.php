<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\EarningsService;
use App\Services\OpenCollab\PayoutService;

/**
 * Contributor earnings & payouts dashboard.
 *
 * Distinct from DashboardPageController (overview) — this is the full
 * financial view: lifetime earnings, transaction history, payout history,
 * and PDF statement downloads.
 *
 * Routes:
 *   GET /contributor/earnings
 */
class ContributorEarningsPageController extends Controller
{
    public function __construct(
        private readonly EarningsService          $earningsService,
        private readonly PayoutService            $payoutService,
        private readonly PayoutRepository         $payoutRepository,
        private readonly ArticlePaymentRepository $paymentRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /contributor/earnings
     */
    public function index()
    {
        $userId = Auth::id();

        die('here');

        $totalEarnings = $this->earningsService->totalEarningsForContributor($userId);
        $breakdown = $this->earningsService->earningsBreakdownForContributor($userId);
        $availableBalance = $this->payoutService->availableBalance($userId);

        // Full transaction history (succeeded + refunded payments)
        $transactionsRaw = $this->paymentRepository->transactionHistoryForContributor($userId, 50);
        $transactions = is_array($transactionsRaw)
            ? ($transactionsRaw['data'] ?? collect([]))
            : $transactionsRaw;

        // Payout history — all statuses, newest first
        $payouts = $this->payoutRepository->forContributor($userId, 50);

        // Summary figures
        $totalPaid = $this->payoutRepository->totalPaidForContributor($userId);
        $totalInFlight = $this->payoutRepository->totalInFlightForContributor($userId);

        return $this->view('open-collab.contributor.earnings.index', [
            'totalEarnings' => $totalEarnings,
            'availableBalance' => $availableBalance,
            'totalPaid' => $totalPaid,
            'totalInFlight' => $totalInFlight,
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
            'site' => SiteContext::slug(),
        ]);
    }
}