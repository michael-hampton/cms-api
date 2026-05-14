<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\OpenCollab\EarningsService;

class DashboardPageController extends Controller
{
    public function __construct(
        private readonly PageRepository     $pageRepository,
        private readonly EarningsService    $earningsService,
        private readonly ActivityRepository $activityRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /contributor/dashboard
     */
    public function index()
    {
        $userId = Auth::id();
        $siteId = SiteContext::getId();

        return $this->view('open-collab.dashboard.show', [
            'articles' => $this->pageRepository->getContributorPages($userId, $siteId),
            'earnings' => [
                'total' => $this->earningsService->totalEarningsForContributor($userId),
                'breakdown' => $this->earningsService->earningsBreakdownForContributor($userId),
            ],
            'activity' => $this->activityRepository->forContributor($userId, 10),
            'currentUser' => Auth::user(),
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