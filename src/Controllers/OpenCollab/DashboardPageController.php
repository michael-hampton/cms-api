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
        private readonly ActivityRepository $activityRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $userId = Auth::id();
        $userId = 1; //todo needs login
        $siteId = SiteContext::getId();

        return $this->view('open-collab.dashboard.show', [
            'articles' => $this->pageRepository->getContributorPages($userId, $siteId),
            'earnings' => [
                'total' => $this->earningsService->totalEarningsForContributor($userId),
                'breakdown' => $this->earningsService->earningsBreakdownForContributor($userId),
            ],
            'activity' => $this->activityRepository->forContributor($userId, 10),
        ]);
    }

    public function earnings()
    {
        $userId = Auth::id();

        // Using the service you already have
        $total = $this->earningsService->totalEarningsForContributor($userId);
        $breakdown = $this->earningsService->earningsBreakdownForContributor($userId);

        // Mocking pending for now, or fetch from a PayoutService if you have one
        $pending = $total;

        return $this->view('open-collab.dashboard.earnings', [
            'earnings' => [
                'total' => $total,
                'breakdown' => $breakdown,
                'pending' => $pending
            ],
            //'payment_details' => $this->profileRepository->getPaymentDetails($userId), // todo
            'site' => SiteContext::slug()
        ]);
    }
}