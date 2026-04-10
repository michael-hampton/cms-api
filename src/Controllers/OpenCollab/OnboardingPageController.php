<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\AuthenticatedUser;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Repositories\OpenCollab\ContractRepository;
use App\Services\OpenCollab\ContributorOnboardingService;

class OnboardingPageController extends Controller
{
    public function __construct(
        private readonly ContributorOnboardingService $onboardingService,
        private ContractRepository                    $contractRepository
    )
    {
        parent::__construct();
    }

    /**
     * GET /onboarding
     * The single entry point for the onboarding flow.
     */
    public function show()
    {
        Auth::$user = new AuthenticatedUser(1, 'Michael Hampton', 'michaelhamptondesign@yahoo.com'); //todo login needs plugging in
        $userId = Auth::id();
        $site = Site::find(SiteContext::getId());

        $pending = $this->onboardingService->pendingSteps($userId, $site);

        if (empty($pending)) {
            return $this->redirect('contributor.dashboard');
        }

        // The current step is always the first one in the pending list
        $currentStep = $pending[0];

        return $this->view('open-collab.onboarding.index', [
            'currentStep' => $currentStep,
            'pendingSteps' => $pending,
            'site' => SiteContext::slug(),
            // Pass specific data needed for steps (e.g. the contract text)
            'contract' => $currentStep === 'contract' ? $this->contractRepository->latestForSite(SiteContext::getId()) : null,
        ]);
    }
}