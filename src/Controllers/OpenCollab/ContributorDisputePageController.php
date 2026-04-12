<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\EarningsDisputeRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;

/**
 * Renders the contributor-facing earnings disputes page.
 *
 * Routes:
 *   GET /contributor/disputes
 */
class ContributorDisputePageController extends Controller
{
    public function __construct(
        private readonly EarningsDisputeRepository $disputeRepository,
        private readonly EarningsLedgerRepository  $ledgerRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /contributor/disputes
     */
    public function index()
    {
        $this->requireAuth();

        $userId = Auth::id();

        $disputes = $this->disputeRepository->forContributor($userId);

        // Load ledger entries the contributor could dispute (not already open)
        $ledgerEntries = $this->ledgerRepository->eligibleForPayout(
            $userId,
            now_datetime()->subDays(30) // all entries — we want the full history for raising new disputes
        );

        // Filter out entries that already have an open dispute
        $openDisputeLedgerIds = $disputes
            ->filter(fn($d) => $d->status === 'open')
            ->pluck('earnings_ledger_id')
            ->toArray();

        $disputableLedgerEntries = $ledgerEntries->filter(
            fn($e) => !in_array($e->id, $openDisputeLedgerIds, true)
        );

        return $this->view('open-collab.contributor.disputes.index', [
            'disputes' => $disputes,
            'disputableLedgerEntries' => $disputableLedgerEntries,
            'pageTitle' => 'Earnings Disputes',
            'activeNav' => 'earnings',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/contributor/dashboard'],
                ['label' => 'Earnings Disputes'],
            ],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }

    private function requireAuth(): void
    {
        if (!Auth::check()) {
            header('Location: /login?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }
    }
}