<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Repositories\OpenCollab\ContractRepository;
use App\Services\OpenCollab\ContributorOnboardingService;

class OnboardingPageController extends Controller
{
    public function __construct(
        private readonly ContributorOnboardingService $onboardingService,
        private readonly ContractRepository $contractRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /onboarding
     */
    public function show()
    {
        $this->requireAuth();

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

        $currentStep = $pending[0];

        return $this->view('open-collab.onboarding.index', [
            'currentStep' => $currentStep,
            'pendingSteps' => $pending,
            'site' => SiteContext::slug(),
            'contract' => $currentStep['step'] === 'contract'
                ? $this->contractRepository->latestForSite($site->id)
                : null,
            'siteGuidelinesVersion' => (int)($site->guidelines_version ?? 1),
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key'),
            'currentUser' => Auth::user(),
        ]);
    }

    private function requireAuth(): void
    {
        if (!Auth::check()) {
            header('Location: /login?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }
    }

    private function serverError(string $msg)
    {
        http_response_code(500);
        return $this->view('errors.500', ['message' => $msg]);
    }
}