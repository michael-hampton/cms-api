<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\PaymentTermsService;

/**
 * Renders the admin scheduled payouts visibility page.
 *
 * Shows two sections:
 *   1. Already-generated pending payouts (from payouts table)
 *   2. Preview of upcoming eligible payouts not yet generated
 *
 * Routes:
 *   GET /admin/payouts/scheduled
 */
class AdminScheduledPayoutsPageController extends Controller
{
    public function __construct(
        private readonly PayoutRepository         $payoutRepository,
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly PaymentTermsService      $paymentTermsService,
        private readonly CreatorBalanceService $creatorBalanceService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/payouts/scheduled
     */
    public function index()
    {
        $siteId = SiteContext::getId();
        $terms = $this->paymentTermsService->forSite($siteId);

        // Section 1: already-generated pending payouts (scheduler already ran)
        $pendingPayouts = $this->payoutRepository->pendingForSite($siteId);

        // Section 2: preview — eligible ledger entries not yet in a payout
        $cutoff = (new \DateTime())->modify("-{$terms->payout_delay_days} days");
        $eligibleByUser = $this->ledgerRepository->eligibleGroupedBySiteAndUser($siteId, $cutoff);

        // Flatten into display rows: [ [user_id, currency, amount, entry_count] ]
        $settledRows = $this->ledgerRepository->settledBalancesBySite($siteId);

        $upcomingRows = [];

        foreach ($settledRows as $row) {
            $amount = (int) $row['amount'];

            if ($amount < $terms->minimum_payout_amount) {
                continue;
            }

            $upcomingRows[] = [
                'user_id' => (int) $row['user_id'],
                'currency' => strtoupper($row['currency'] ?? 'GBP'),
                'amount' => $amount,
                'below_min' => false,
            ];
        }

        return $this->view('open-collab.admin.payouts.scheduled', [
            'pendingPayouts' => $pendingPayouts,
            'upcomingRows' => $upcomingRows,
            'terms' => $terms,
            'cutoffDate' => $cutoff->format('d M Y'),
            'pageTitle' => 'Payout Schedule',
            'activeNav' => 'payouts',
            'breadcrumbs' => [
                ['label' => 'Payouts', 'url' => '/admin/payouts'],
                ['label' => 'Schedule'],
            ],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}