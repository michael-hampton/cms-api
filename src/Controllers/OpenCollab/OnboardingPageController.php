<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesContentRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\ViewModels\OpenCollab\OnboardingPageViewModel;

class OnboardingPageController extends Controller
{
    use AuthorizesSitePagePermissions;

    public function __construct(
        private readonly ContributorOnboardingService $onboardingService,
        private readonly ContractRepository $contractRepository,
        private readonly GuidelinesRepository $guidelinesRepository,
        private readonly GuidelinesContentRepository $guidelinesContentRepository,
        private readonly OpenCollabAuthorizationService $authorization,
        private readonly ContributorProfileRepository $contributorProfileRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /onboarding
     */
    public function show()
    {
        if ($response = $this->authorizeSitePagePermissions(['onboarding.view'])) {
            return $response;
        }

        $userId = Auth::id();
        $site = Site::find(SiteContext::getId());

        if (!$site) {
            return $this->serverError('Site configuration missing.');
        }

        $pending = $this->onboardingService->pendingSteps($userId, $site);

        if (empty($pending)) {
            header('Location: /contributor/dashboard');
            exit;
        }

        $viewModel = new OnboardingPageViewModel($pending, $site);
        $publishedGuidelines = $viewModel->currentStepName() === 'guidelines'
            ? $this->guidelinesContentRepository->latestPublishedForSite($site->id)
            : null;

        return $this->view('open-collab.onboarding.index', [
            'vm' => $viewModel,
            // Passed separately because the view needs them for JS/Stripe init,
            // not for step logic.
            'contract' => $viewModel->currentStepName() === 'contract'
                ? $this->contractRepository->latestForSite($site->id)
                : null,
            'siteGuidelines' => $publishedGuidelines,
            'siteGuidelinesVersion' => $publishedGuidelines?->version
                ?? $this->guidelinesRepository->latestVersion($site->id),
            'site' => SiteContext::slug(),
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key'),
            'currentUser' => Auth::user(),
            'profile' => $this->contributorProfileRepository->findByUserId(Auth::id()),
        ]);
    }

    private function serverError(string $msg)
    {
        http_response_code(500);
        return $this->view('errors.500', ['message' => $msg]);
    }
}
