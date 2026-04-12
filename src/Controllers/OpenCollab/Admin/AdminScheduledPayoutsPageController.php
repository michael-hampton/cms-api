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
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/payouts/scheduled
     */
    public function index()
    {
        $this->requireAdmin();

        $siteId = SiteContext::getId();
        $terms = $this->paymentTermsService->forSite($siteId);

        // Section 1: already-generated pending payouts (scheduler already ran)
        $pendingPayouts = $this->payoutRepository->pendingForSite($siteId);

        // Section 2: preview — eligible ledger entries not yet in a payout
        $cutoff = (new \DateTime())->modify("-{$terms->payout_delay_days} days");
        $eligibleByUser = $this->ledgerRepository->eligibleGroupedBySiteAndUser($siteId, $cutoff);

        // Flatten into display rows: [ [user_id, currency, amount, entry_count] ]
        $upcomingRows = [];
        foreach ($eligibleByUser as $userId => $entries) {
            $byCurrency = [];
            foreach ($entries as $entry) {
                $currency = strtoupper($entry['currency'] ?? 'GBP');
                $byCurrency[$currency] = ($byCurrency[$currency] ?? 0) + (int)$entry['amount'];
            }
            foreach ($byCurrency as $currency => $total) {
                if ($total < $terms->minimum_payout_amount) {
                    continue; // below threshold — won't be scheduled
                }
                $upcomingRows[] = [
                    'user_id' => $userId,
                    'currency' => $currency,
                    'amount' => $total,
                    'below_min' => false,
                ];
            }
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

    private function requireAdmin(): void
    {
        $user = Auth::getUser();
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'agent'], true)) {
            header('Location: /login');
            exit;
        }
    }
}