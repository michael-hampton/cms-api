<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\EarningsDisputeRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Services\OpenCollab\Surfaces\SurfaceResolver;

/**
 * Renders the contributor-facing earnings disputes page.
 */
class ContributorDisputePageController extends Controller
{
    public function __construct(
        private readonly EarningsDisputeRepository $disputeRepository,
        private readonly EarningsLedgerRepository  $ledgerRepository,
        private readonly SurfaceResolver           $surfaceResolver,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $userId = Auth::id();
        $site = SiteContext::slug();
        $siteId = SiteContext::getId();
        $surface = 'disputes.index';

        $disputes = $this->disputeRepository->forContributor($userId);

        /**
         * Disputes should be available once an earnings entry exists on the
         * contributor ledger. Previously this reused payout eligibility with a
         * 30-day cutoff, which hid the "Raise a dispute" action for new sales.
         */
        $ledgerEntries = $this->ledgerRepository->settledAvailableForPayout($userId, $siteId);
        $disputedLedgerIds = $disputes
            ->pluck('earnings_ledger_id')
            ->map(fn($id) => (int)$id)
            ->toArray();

        $eligibleEntries = $ledgerEntries
            ->filter(fn($entry) => !in_array((int)$entry->id, $disputedLedgerIds, true))
            ->map(fn($entry) => [
                'id' => (int)$entry->id,
                'amount' => (int)$entry->amount,
                'currency' => strtoupper($entry->currency ?? 'GBP'),
                'type' => ucfirst($entry->type ?? 'sale'),
                'earned_at' => $entry->earned_at?->format('d M Y') ?? '',
            ])->values()->toArray();

        return $this->view('open-collab.contributor.disputes.index', [
            'surface' => $surface,
            'sections' => $this->surfaceResolver->manifest($surface, $site),
            'surfaceContext' => [
                'disputes' => [
                    'eligible_entries' => $eligibleEntries,
                ],
            ],
            'pageTitle' => 'Earnings Disputes',
            'activeNav' => 'earnings',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/contributor/dashboard'],
                ['label' => 'Earnings Disputes'],
            ],
            'currentUser' => Auth::user(),
            'site' => $site,
        ]);
    }
}
