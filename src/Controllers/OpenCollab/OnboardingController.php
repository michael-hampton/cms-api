<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Enums\OpenCollab\AgeVerificationMethod;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;
use App\Requests\OpenCollab\AcknowledgeGuidelinesRequest;
use App\Requests\OpenCollab\SignContractRequest;
use App\Requests\OpenCollab\StoreOnboardingProfileRequest;
use App\Requests\OpenCollab\StorePaymentDetailsRequest;
use App\Resources\OpenCollab\OnboardingStatusResource;
use App\Services\OpenCollab\ContributorOnboardingService;
use RuntimeException;

/**
 * Handles contributor onboarding steps.
 *
 * All endpoints require authentication. The frontend drives the user through
 * steps in order; the backend validates each step independently.
 *
 * Routes:
 *   GET  /api/{site}/open-collab/onboarding/status
 *   POST /api/{site}/open-collab/onboarding/profile
 *   POST /api/{site}/open-collab/onboarding/payment
 *   GET  /api/{site}/open-collab/onboarding/contract
 *   POST /api/{site}/open-collab/onboarding/contract
 *   POST /api/{site}/open-collab/onboarding/guidelines
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly ContributorOnboardingService $onboardingService,
        private readonly ContributorProfileRepository $profileRepository,
        private readonly ContractRepository           $contractRepository,
        private readonly GuidelinesRepository         $guidelinesRepository,
        private readonly ContributorProfileRepository $contributorProfileRepository
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/onboarding/status
     */
    public function status(): JsonResponse
    {
        $site = $this->currentSite();
        $userId = Auth::id();
        $pending = $this->onboardingService->pendingSteps($userId, $site);

        return $this->jsonResponse(
            (new OnboardingStatusResource(['pending_steps' => $pending]))->toArray()
        );
    }

    /**
     * POST /api/{site}/open-collab/onboarding/profile
     */
    public function storeProfile(StoreOnboardingProfileRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $userId = Auth::id();

            $this->profileRepository->createOrUpdate($userId, [
                'bio' => $data['bio'],
                'avatar' => $data['avatar'] ?? null,
            ]);

            return $this->successResponse('Profile saved.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }

    /**
     * POST /api/{site}/open-collab/onboarding/payment
     * Raw card data MUST NOT be sent here — Stripe.js tokenises first.
     */
    public function storePaymentDetails(StorePaymentDetailsRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $userId = Auth::id();

            $this->profileRepository->markPaymentSetup(
                userId: $userId,
                stripeToken: $data['stripe_token'] ?? $data['payment_method_type'],
            );

            return $this->successResponse('Payment details saved.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }

    /**
     * GET /api/{site}/open-collab/onboarding/contract
     */
    public function getContract(): JsonResponse
    {
        $site = $this->currentSite();
        $contract = $this->contractRepository->latestForSite($site->id);

        if (!$contract) {
            return $this->errorResponse('No contract available for this site.', 404);
        }

        return $this->jsonResponse([
            'id' => $contract->id,
            'version' => $contract->version,
            'content' => $contract->content,
        ]);
    }

    /**
     * POST /api/{site}/open-collab/onboarding/contract
     */
    public function signContract(SignContractRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $userId = Auth::id();
            $site = $this->currentSite();

            $contract = $this->contractRepository->latestForSite($site->id);

            if (!$contract) {
                return $this->errorResponse('No contract found for this site.', 404);
            }

            if ((int)$data['contract_id'] !== $contract->id) {
                return $this->errorResponse(
                    'Contract version mismatch. Please reload and try again.',
                    409
                );
            }

            if ($this->contractRepository->hasSigned($userId, $contract->id)) {
                return $this->successResponse('Contract already signed.');
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $this->contractRepository->recordSignature($userId, $contract->id, $ip);

            return $this->successResponse('Contract signed.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }

    public function updateAgeVerification(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $userId = Auth::id();

            $this->contributorProfileRepository->updateDob($userId, $data['date_of_birth']);

            $this->contributorProfileRepository->markAgeVerified($userId, AgeVerificationMethod::SelfDeclared);

            return $this->successResponse('Profile saved.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }

    /**
     * POST /api/{site}/open-collab/onboarding/guidelines
     */
    public function acknowledgeGuidelines(AcknowledgeGuidelinesRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $userId = Auth::id();
            $site = $this->currentSite();

            $currentVersion = (int)($site->guidelines_version ?? 1);

            if ((int)$data['version'] < $currentVersion) {
                return $this->errorResponse(
                    'Guidelines have been updated. Please review the latest version.',
                    409
                );
            }

            if ($this->guidelinesRepository->hasAcknowledged($userId, $site->id, $currentVersion)) {
                return $this->successResponse('Guidelines already acknowledged.');
            }

            $this->guidelinesRepository->record($userId, $site->id, $currentVersion);

            return $this->successResponse('Guidelines acknowledged.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }

    // -------------------------------------------------------------------------

    private function currentSite(): Site
    {
        $site = Site::find(SiteContext::getId());

        if (!$site) {
            throw new RuntimeException('Site not found in context.');
        }

        return $site;
    }
}