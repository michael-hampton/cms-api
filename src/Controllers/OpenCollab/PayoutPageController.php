<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\Surfaces\SurfaceResolver;

/**
 * Renders the contributor-facing payout screen.
 */
class PayoutPageController extends Controller
{
    use AuthorizesSitePagePermissions;

    public function __construct(
        private readonly SurfaceResolver                $surfaceResolver,
        private readonly OpenCollabAuthorizationService $authorization,
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
        $surface = 'payouts.index';

        return $this->view('open-collab.payouts.index', [
            'surface' => $surface,
            'sections' => $this->surfaceResolver->manifest($surface, $site),
            'surfaceContext' => [],
            'currentUser' => Auth::user(),
            'site' => $site,
        ]);
    }
}
