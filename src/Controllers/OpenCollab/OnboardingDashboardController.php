<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Services\OpenCollab\ContributorOnboardingService;
use RuntimeException;

/**
 * Serves the onboarding dashboard view and its status API endpoint.
 *
 * Routes:
 *   GET /contributor/onboarding                   → dashboard view
 *   GET /api/{site}/open-collab/onboarding/status → JSON status (used by dashboard JS)
 *
 * NOTE: The existing OnboardingController::status() returns only {pending_steps}.
 * This controller extends that with the richer shape the dashboard expects.
 * Both shapes are handled gracefully by the dashboard JS normaliser.
 */
class OnboardingDashboardController extends Controller
{
    public function __construct(
        private readonly ContributorOnboardingService $onboardingService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /contributor/onboarding
     * Renders the dashboard view. The page itself is driven by the status API.
     */
    public function index()
    {
        $site = $this->currentSite();
        $currentUser = Auth::user();

        return $this->view('open-collab/onboarding/dashboard', [
            'site' => $site->slug ?? SiteContext::getId(),
            'currentUser' => $currentUser,
        ]);
    }

    private function currentSite(): Site
    {
        $site = Site::find(SiteContext::getId());

        if (!$site) {
            throw new RuntimeException('Site not found in context.');
        }

        return $site;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * GET /api/{site}/open-collab/onboarding/status
     *
     * Returns the richer status shape for the dashboard.
     * This augments (and is compatible with) the existing slim status endpoint
     * on OnboardingController, which returns only {pending_steps}.
     *
     * Response shape:
     * {
     *   "isComplete":     bool,
     *   "completedCount": int,
     *   "totalSteps":     int,
     *   "completedSteps": string[],
     *   "pendingSteps":   [{step, reason, meta}],
     * }
     */
    public function status(): JsonResponse
    {
        $site = $this->currentSite();
        $userId = Auth::id();

        $pendingSteps = $this->onboardingService->pendingSteps($userId, $site);
        $completedSteps = $this->onboardingService->completedSteps($userId, $site);
        $totalSteps = count($pendingSteps) + count($completedSteps);

        return $this->resourceResponse([
            'isComplete' => empty($pendingSteps),
            'completedCount' => count($completedSteps),
            'totalSteps' => $totalSteps,
            'completedSteps' => $completedSteps,
            'pendingSteps' => $pendingSteps,
        ]);
    }
}