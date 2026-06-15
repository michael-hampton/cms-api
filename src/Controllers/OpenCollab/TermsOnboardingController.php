<?php

namespace App\Controllers\OpenCollab;

use App\Actions\OpenCollab\Legal\AcceptTermsVersionAction;
use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\TermsVersionRepository;
use App\Requests\OpenCollab\AcceptTermsRequest;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\TermsAcceptanceRequirementService;
use App\ViewModels\OpenCollab\OnboardingLegalDocumentViewModelFactory;

class TermsOnboardingController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly TermsAcceptanceRequirementService $requirements,
        private readonly TermsVersionRepository $repository,
        private readonly AcceptTermsVersionAction $acceptTerms,
        private readonly OnboardingLegalDocumentViewModelFactory $legalDocumentFactory,
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function show(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.view'])) {
            return $response;
        }

        $terms = $this->requirements->currentVisibleVersion(SiteContext::getId());

        if (!$terms) {
            return $this->errorResponse('No published Terms and Conditions are available for this site.', 404);
        }

        return $this->jsonResponse([
            'terms' => $this->legalDocumentFactory->forTerms($terms),
            'acceptance_required' => $this->requirements->requiresAcceptance(Auth::id(), SiteContext::getId()),
        ]);
    }

    public function accept(AcceptTermsRequest $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.accept'])) {
            return $response;
        }

        $siteId = SiteContext::getId();
        try {
            $data = $request->validated();
        } catch (ValidationException $exception) {
            return $this->errorResponse('Validation failed', 422, $exception->getErrors());
        }

        $required = $this->requirements->currentRequiredVersion($siteId);

        if (!$required) {
            return $this->errorResponse('No Terms and Conditions require acceptance.', 404);
        }

        if ((int)$data['terms_version_id'] !== (int)$required->id) {
            return $this->errorResponse('The Terms and Conditions version is no longer current. Refresh and try again.', 409);
        }

        $terms = $this->repository->findForSite((int)$data['terms_version_id'], $siteId);

        if (!$terms) {
            return $this->errorResponse('Terms version not found.', 404);
        }

        $acceptance = $this->acceptTerms->execute(
            $terms,
            Auth::id(),
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            'onboarding',
        );

        return $this->jsonResponse([
            'message' => 'Terms and Conditions accepted.',
            'acceptance_id' => $acceptance->id,
            'terms_version_id' => $acceptance->terms_version_id,
        ]);
    }
}
