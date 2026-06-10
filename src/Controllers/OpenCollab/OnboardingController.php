<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Enums\Cms\CustomFieldStorageType;
use App\Enums\OpenCollab\AgeVerificationMethod;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Models\User;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;
use App\Requests\OpenCollab\AcknowledgeGuidelinesRequest;
use App\Requests\OpenCollab\SignContractRequest;
use App\Requests\OpenCollab\StoreOnboardingProfileRequest;
use App\Requests\OpenCollab\StorePaymentDetailsRequest;
use App\Resources\OpenCollab\OnboardingStatusResource;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\ContributorPaymentMethodService;
use App\Services\OpenCollab\ContributorProfileFieldConfigService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
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
 *   POST /api/{site}/open-collab/onboarding/steps/profile/complete
 *   POST /api/{site}/open-collab/onboarding/payment
 *   POST /api/{site}/open-collab/onboarding/steps/payment/complete
 *   GET  /api/{site}/open-collab/onboarding/contract
 *   POST /api/{site}/open-collab/onboarding/contract
 *   POST /api/{site}/open-collab/onboarding/guidelines
 *   POST /api/{site}/open-collab/onboarding/age-verification
 *   POST /api/{site}/open-collab/onboarding/steps/kyc-verification/complete
 */
class OnboardingController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly ContributorOnboardingService         $onboardingService,
        private readonly ContributorProfileRepository         $profileRepository,
        private readonly ContractRepository                   $contractRepository,
        private readonly GuidelinesRepository                 $guidelinesRepository,
        private readonly OpenCollabAuthorizationService       $authorization,
        private readonly ContributorPaymentMethodService      $paymentMethodService,
        private readonly ContributorProfileFieldConfigService $profileFieldConfigService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/onboarding/status
     */
    public function status(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        $site = $this->currentSite();
        $userId = Auth::id();
        $pending = $this->onboardingService->pendingSteps($userId, $site);

        return $this->jsonResponse(
            (new OnboardingStatusResource(['pending_steps' => $pending]))->toArray()
        );
    }

    private function currentSite(): Site
    {
        $site = Site::find(SiteContext::getId());

        if (!$site) {
            throw new RuntimeException('Site not found in context.');
        }

        return $site;
    }

    /**
     * POST /api/{site}/open-collab/onboarding/profile
     *
     * Persists profile fields without advancing the onboarding step.
     * The frontend calls this on "Save progress" and again before calling
     * steps/profile/complete.
     */
    public function storeProfile(StoreOnboardingProfileRequest $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        try {
            $site = $this->currentSite();
            $userId = Auth::id();

            $this->ensureContributorProfileStarted($userId, $site);

            $data = $request->validated();

            $fields = $this->profileFieldConfigService->activeFieldsForSite($site);

            $profileData = [];

            foreach ($fields as $field) {
                $key = (string)$field->key;

                if (!array_key_exists($key, $data)) {
                    continue;
                }

                $value = $data[$key];

                if ($key === 'writing_samples') {
                    $urls = $value['url'] ?? [];
                    $titles = $value['title'] ?? [];

                    $samples = [];

                    foreach ((array)$urls as $index => $url) {
                        $url = trim((string)$url);

                        if ($url === '') {
                            continue;
                        }

                        $samples[] = [
                            'url' => $url,
                            'title' => trim((string)($titles[$index] ?? '')),
                        ];
                    }

                    $column = $field->profile_column ?: $key;
                    $profileData[$column] = json_encode($samples);

                    continue;
                }

                if (($field->storage_type ?? null) === CustomFieldStorageType::ProfileColumn->value) {
                    $column = $field->profile_column ?: $key;
                    $profileData[$column] = $value;
                    continue;
                }
            }

            // Avatar can still be passed separately by existing frontend code.
            if (array_key_exists('avatar', $data)) {
                $profileData['avatar'] = $data['avatar'];
            }

            $this->profileRepository->createOrUpdate($userId, $profileData);

            $this->onboardingService->touchActivity($userId, $site);

            return $this->successResponse('Profile saved.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }

    private function ensureContributorProfileStarted(int $userId, Site $site): void
    {
        $this->onboardingService->start($userId, $site->id);
    }

    /**
     * POST /api/{site}/open-collab/onboarding/steps/profile/complete
     *
     * Validates the saved profile is complete and advances the step.
     * Frontend calls this only when the user clicks "Save & continue".
     */
    public function completeProfileStep(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        $site = $this->currentSite();
        $result = $this->onboardingService->completeProfileStep(Auth::id(), $site);

        if (!$result['ok']) {
            return $this->errorResponse('Validation failed', 422, $result['errors']);
        }

        return $this->jsonResponse([
            'message' => 'Profile step completed.',
            'onboarding' => $result['status'],
        ]);
    }

    /**
     * POST /api/{site}/open-collab/onboarding/payment
     *
     * Persists payment method details without advancing the onboarding step.
     * Raw card data MUST NOT be sent here — Stripe.js tokenises first.
     */
    public function storePaymentDetails(StorePaymentDetailsRequest $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        try {
            $data = $request->validated();
            $userId = Auth::id();

            if (($data['payment_method_type'] ?? null) === 'stripe') {
                if (empty($data['payment_method_id'])) {
                    return $this->errorResponse('A Stripe payment method is required when using card payments.', 422);
                }

                $result = $this->paymentMethodService->addForUser(
                    $this->currentUser(),
                    (string)$data['payment_method_id'],
                    $data['tax_country'] ?? null,
                    true,
                );

                if (!($result['success'] ?? false)) {
                    return $this->errorResponse($result['message'] ?? 'Could not save payment method.', 422);
                }

                return $this->successResponse('Payment details saved.', [
                    'payment_methods' => $result['payment_methods'] ?? [],
                    'default_payment_method_id' => $result['default_payment_method_id'] ?? null,
                ]);
            }

            $this->profileRepository->markPaymentSetup(
                userId: $userId,
                paymentDetails: $data['stripe_token'] ?? $data['payment_method_type'],
                paymentMethodType: 'bank_transfer',
                taxCountry: $data['tax_country'] ?? null,
            );

            $this->onboardingService->touchActivity($userId, $this->currentSite());

            return $this->successResponse('Payment details saved.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }

    private function currentUser(): User
    {
        $user = User::find(Auth::id());

        if (!$user) {
            throw new RuntimeException('Authenticated user could not be loaded.');
        }

        return $user;
    }

    public function paymentMethods(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        $result = $this->paymentMethodService->listForUser($this->currentUser());

        if (!($result['success'] ?? false)) {
            return $this->errorResponse($result['message'] ?? 'Could not load payment methods.', 500);
        }

        return $this->jsonResponse([
            'payment_methods' => $result['payment_methods'] ?? [],
            'default_payment_method_id' => $result['default_payment_method_id'] ?? null,
        ]);
    }

    public function setDefaultPaymentMethod(Request $request, string $paymentMethodId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        $result = $this->paymentMethodService->setDefaultForUser($this->currentUser(), $paymentMethodId);

        if (!($result['success'] ?? false)) {
            return $this->errorResponse($result['message'] ?? 'Could not update payment method.', 422);
        }

        return $this->successResponse('Default payment method updated.', [
            'payment_methods' => $result['payment_methods'] ?? [],
            'default_payment_method_id' => $result['default_payment_method_id'] ?? null,
        ]);
    }

    public function removePaymentMethod(string $paymentMethodId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        $result = $this->paymentMethodService->removeForUser($this->currentUser(), $paymentMethodId);

        if (!($result['success'] ?? false)) {
            $status = ($result['error_code'] ?? null) === 'unauthorized' ? 403 : 422;
            return $this->errorResponse($result['message'] ?? 'Could not remove payment method.', $status);
        }

        return $this->successResponse('Payment method removed.', [
            'payment_methods' => $result['payment_methods'] ?? [],
            'default_payment_method_id' => $result['default_payment_method_id'] ?? null,
        ]);
    }

    /**
     * POST /api/{site}/open-collab/onboarding/steps/payment/complete
     *
     * Validates that payment details have been saved and advances the step.
     * Frontend calls this only when the user clicks "Confirm & continue".
     */
    public function completePaymentStep(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        $site = $this->currentSite();
        $result = $this->onboardingService->completePaymentStep(Auth::id(), $site);

        if (!$result['ok']) {
            return $this->errorResponse('Validation failed', 422, $result['errors']);
        }

        return $this->jsonResponse([
            'message' => 'Payment step completed.',
            'onboarding' => $result['status'],
        ]);
    }

    /**
     * POST /api/{site}/open-collab/onboarding/steps/kyc-verification/complete
     */
    public function completeKycVerificationStep(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        $site = $this->currentSite();
        $result = $this->onboardingService->completeKycVerificationStep(Auth::id(), $site);

        if (!$result['ok']) {
            return $this->errorResponse('Validation failed', 422, $result['errors']);
        }

        return $this->jsonResponse([
            'message' => 'KYC verification step completed.',
            'onboarding' => $result['status'],
        ]);
    }

    /**
     * GET /api/{site}/open-collab/onboarding/contract
     */
    public function getContract(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view', 'contract.sign'])) {
            return $response;
        }

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

    // -------------------------------------------------------------------------

    /**
     * POST /api/{site}/open-collab/onboarding/contract
     */
    public function signContract(SignContractRequest $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view', 'contract.sign'])) {
            return $response;
        }

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
                $this->onboardingService->completeStep($userId, $site, 'contract');
                return $this->successResponse('Contract already signed.');
            }

            $this->contractRepository->recordSignature(
                $userId,
                $contract->id,
                $this->clientIp($request),
            );

            $this->onboardingService->completeStep($userId, $site, 'contract');

            $this->onboardingService->touchActivity($userId, $this->currentSite());

            return $this->successResponse('Contract signed.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }

    /**
     * Reads the client IP from the request object rather than from the $_SERVER
     * superglobal, keeping infrastructure details out of the controller body.
     * Falls back to '0.0.0.0' if the header is absent.
     */
    private function clientIp(Request $request): string
    {
        return $request->ip() ?? '0.0.0.0';
    }

    /**
     * POST /api/{site}/open-collab/onboarding/age-verification
     */
    public function updateAgeVerification(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view'])) {
            return $response;
        }

        try {
            $data = $request->all();
            $userId = Auth::id();
            $site = $this->currentSite();

            $this->profileRepository->updateDob($userId, $data['date_of_birth']);
            $this->profileRepository->markAgeVerified($userId, AgeVerificationMethod::SelfDeclared);

            $this->onboardingService->completeStep($userId, $site, 'age_verification');

            $this->onboardingService->touchActivity($userId, $this->currentSite());

            return $this->successResponse('Age verification saved.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }

    /**
     * POST /api/{site}/open-collab/onboarding/guidelines
     */
    public function acknowledgeGuidelines(AcknowledgeGuidelinesRequest $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['onboarding.view', 'guideline.acknowledge'])) {
            return $response;
        }

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
                $this->onboardingService->completeStep($userId, $site, 'guidelines');
                return $this->successResponse('Guidelines already acknowledged.');
            }

            $this->guidelinesRepository->record($userId, $site->id, $currentVersion);

            $this->onboardingService->completeStep($userId, $site, 'guidelines');

            $this->onboardingService->touchActivity($userId, $this->currentSite());

            return $this->successResponse('Guidelines acknowledged.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        }
    }
}
