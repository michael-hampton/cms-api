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
        $surface = 'disputes.index';

        $disputes = $this->disputeRepository->forContributor($userId);
        $ledgerEntries = $this->ledgerRepository->eligibleForPayout($userId, now_datetime()->subDays(30));
        $openDisputeLedgerIds = $disputes
            ->filter(fn($d) => $d->status === 'open')
            ->pluck('earnings_ledger_id')
            ->toArray();

        $eligibleEntries = $ledgerEntries
            ->filter(fn($entry) => !in_array($entry->id, $openDisputeLedgerIds, true))
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
