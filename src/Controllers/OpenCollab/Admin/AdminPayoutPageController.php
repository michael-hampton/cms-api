<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\Surfaces\SurfaceResolver;

/**
 * Renders the admin payout management page.
 * All payout data is loaded client-side via PayoutController::adminIndex.
 *
 * Routes:
 *   GET /admin/payouts
 */
class AdminPayoutPageController extends Controller
{
    public function __construct(
        private readonly SurfaceResolver $surfaceResolver,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/payouts
     */
    public function index()
    {
        $site = SiteContext::slug();
        $surface = 'admin.payouts.index';

        return $this->view('open-collab.admin.payouts.index', [
            'surface' => $surface,
            'sections' => $this->surfaceResolver->manifest($surface, $site),
            'surfaceContext' => [],
            'pageTitle' => 'Payout Management',
            'activeNav' => 'payouts',
            'breadcrumbs' => [['label' => 'Payouts']],
            'currentUser' => Auth::user(),
            'site' => $site,
        ]);
    }
}
