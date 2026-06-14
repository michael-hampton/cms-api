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
use App\Services\OpenCollab\ContributorProfileFieldConfigService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\TermsAcceptanceRequirementService;
use App\ViewModels\OpenCollab\OnboardingLegalDocumentViewModelFactory;
use App\ViewModels\OpenCollab\OnboardingPageViewModel;
use App\ViewModels\OpenCollab\ProfileStepViewModel;

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
        private readonly ContributorProfileFieldConfigService $profileFieldConfigService,
        private readonly OnboardingLegalDocumentViewModelFactory $legalDocumentFactory,
        private readonly TermsAcceptanceRequirementService $termsRequirementService,
    ) {
        parent::__construct();
    }

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

        $profile = $this->contributorProfileRepository->findByUserId($userId);
        $profileFields = $this->profileFieldConfigService->activeFieldsForSite($site);
        $profileStep = ProfileStepViewModel::fromFields($profileFields, $profile);
        $viewModel = new OnboardingPageViewModel(
            pendingSteps: $pending,
            site: $site,
            profileStep: $profileStep,
        );

        $terms = $viewModel->currentStepName() === 'terms'
            ? $this->termsRequirementService->currentVisibleVersion((int)$site->id)
            : null;
        $contract = $viewModel->currentStepName() === 'contract'
            ? $this->contractRepository->latestForSite($site->id)
            : null;
        $publishedGuidelines = $viewModel->currentStepName() === 'guidelines'
            ? $this->guidelinesContentRepository->latestPublishedForSite($site->id)
            : null;

        return $this->view('open-collab.onboarding.index', [
            'vm' => $viewModel,
            'terms' => $terms,
            'termsDisplay' => $this->legalDocumentFactory->forTerms($terms),
            'contract' => $contract,
            'contractDisplay' => $this->legalDocumentFactory->forContract($contract),
            'siteGuidelines' => $publishedGuidelines,
            'guidelinesDisplay' => $this->legalDocumentFactory->forGuideline($publishedGuidelines),
            'siteGuidelinesVersion' => $publishedGuidelines?->version
                ?? $this->guidelinesRepository->latestVersion($site->id),
            'site' => SiteContext::slug(),
            'siteSlug' => SiteContext::slug(),
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key'),
            'currentUser' => Auth::user(),
            'profile' => $profile,
            'profileFields' => $viewModel->currentStepName() === 'profile'
                ? $profileFields
                : collect([]),
        ]);
    }

    private function serverError(string $msg)
    {
        http_response_code(500);
        return $this->view('errors.500', ['message' => $msg]);
    }
}
