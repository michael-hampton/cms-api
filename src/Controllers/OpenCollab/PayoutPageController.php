<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\PayoutService;
use App\Services\OpenCollab\Surfaces\SurfaceResolver;

/**
 * Renders the contributor-facing payout screen.
 */
class PayoutPageController extends Controller
{
    use AuthorizesSitePagePermissions;

    public function __construct(
        private readonly PayoutService                  $payoutService,
        private readonly PayoutRepository               $payoutRepository,
        private readonly OpenCollabAuthorizationService $authorization,
        private readonly SurfaceResolver                $surfaceResolver,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if ($response = $this->authorizeSitePagePermissions(['payout.request', 'payout.view'])) {
            return $response;
        }

        $site = SiteContext::slug();

        return $this->view('open-collab.payouts.index', [
            'surface' => 'payouts.index',
            'sections' => $this->surfaceResolver->resolve('payouts.index', $site),
            'currentUser' => Auth::user(),
            'site' => $site,
        ]);
    }
}
