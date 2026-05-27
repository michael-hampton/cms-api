<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\PayoutService;

/**
 * Renders the contributor-facing payout screen.
 *
 * Mirrors DashboardPageController in structure.
 * The payout screen shows:
 *   (a) current available balance
 *   (b) list of past payout requests with their statuses
 *   (c) a "Request payout" button
 *
 * Payout method management lives in settings (ContributorAccountPageController).
 *
 * Routes:
 *   GET /contributor/payouts
 */
class PayoutPageController extends Controller
{
    use AuthorizesSitePagePermissions;

    public function __construct(
        private readonly PayoutService    $payoutService,
        private readonly PayoutRepository $payoutRepository,
        private readonly OpenCollabAuthorizationService $authorization,
    )
    {
        parent::__construct();
    }

    /**
     * GET /contributor/payouts
     */
    public function index()
    {
        if ($response = $this->authorizeSitePagePermissions(['payout.request', 'payout.view'])) {
            return $response;
        }

        return $this->view('open-collab.payouts.index', [
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}
