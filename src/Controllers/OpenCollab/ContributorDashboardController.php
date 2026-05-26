<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Resources\OpenCollab\EarningsResource;
use App\Services\OpenCollab\EarningsService;

class ContributorDashboardController extends Controller
{
    public function __construct(
        private readonly EarningsService $earningsService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/dashboard
     * Returns the authenticated contributor's earnings summary.
     */
    public function show(): JsonResponse
    {
        $contributorId = Auth::id();

        $total = $this->earningsService->totalEarningsForContributor($contributorId);
        $breakdown = $this->earningsService->earningsBreakdownForContributor($contributorId);

        return $this->jsonResponse(
            (new EarningsResource(
                [
                    'breakdown' => $breakdown,
                    'total_pence' => $total
                ]
            ))->toArray()
        );
    }
}
