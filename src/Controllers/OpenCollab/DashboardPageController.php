<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Services\OpenCollab\Dashboard\WidgetResolver;
use App\Services\OpenCollab\EarningsService;

class DashboardPageController extends Controller
{
    public function __construct(
        private readonly EarningsService $earningsService,
        private readonly WidgetResolver  $widgetResolver,
    )
    {
        parent::__construct();
    }

    /**
     * GET /contributor/dashboard
     */
    public function index()
    {
        $user = User::hydrateStatic(Auth::getUser());
        $widgets = $this->widgetResolver->resolveForUser($user);

        // Pass only keys and titles to the view.
        // Actual data is fetched per-widget via the JS widget manager.
        $widgetManifest = array_map(
            fn($w) => ['key' => $w->key(), 'title' => $w->title()],
            $widgets
        );

        return $this->view('open-collab.dashboard-new.show', [
            'widgets' => $widgetManifest,
            'currentUser' => $user,
            'site' => SiteContext::slug(),
        ]);
    }

    /**
     * GET /contributor/earnings
     */
    public function earnings()
    {
        $userId = Auth::id();
        $total = $this->earningsService->totalEarningsForContributor($userId);

        return $this->view('open-collab.dashboard.earnings', [
            'earnings' => [
                'total' => $total,
                'breakdown' => $this->earningsService->earningsBreakdownForContributor($userId),
                'pending' => $total, // TODO: replace with a dedicated PayoutService balance
            ],
            'paymentDetails' => null, // TODO: inject ContributorProfileRepository if needed
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}