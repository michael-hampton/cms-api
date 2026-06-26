<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\Surfaces\SurfaceResolver;

class ContributorEarningsPageController extends Controller
{
    public function __construct(
        private readonly PayoutRepository $payoutRepository,
        private readonly SurfaceResolver  $surfaceResolver,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $site = SiteContext::slug();
        $surface = 'earnings.index';

        return $this->view('open-collab.contributor.earnings.index', [
            'surface' => $surface,
            'sections' => $this->surfaceResolver->manifest($surface, $site),
            'surfaceContext' => [
                'earnings' => [
                    'payouts' => $this->payoutRepository->forContributor(Auth::id(), 50)->map(fn($payout) => [
                        'id' => $payout->id,
                        'amount' => (int)$payout->amount,
                        'amount_pence' => (int)$payout->amount,
                        'currency' => strtoupper($payout->currency ?? 'GBP'),
                        'status' => $payout->status ?? 'pending',
                        'rejection_reason' => $payout->rejection_reason ?? null,
                        'created_at' => is_object($payout->created_at) && method_exists($payout->created_at, 'format')
                            ? $payout->created_at->format('Y-m-d H:i:s')
                            : (string)($payout->created_at ?? ''),
                    ])->toArray(),
                ],
            ],
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
