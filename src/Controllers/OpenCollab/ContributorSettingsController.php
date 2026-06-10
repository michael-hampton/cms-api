<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Resources\OpenCollab\ContributorProfileResource;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\ContributorProfileFieldConfigService;
use App\Services\OpenCollab\ContributorProfileService;
use App\Services\OpenCollab\DynamicFieldValidator;
use Exception;

/**
 * Self-service settings endpoints consumed by the settings/index view.
 *
 * Routes (all under /api/{site}/open-collab/contributor/):
 *   POST   avatar            — upload / replace profile photo
 *   DELETE avatar            — remove profile photo
 *   POST   expertise         — save expertise tag list
 *   POST   profile           — update profile fields (core + dynamic)
 *
 * Ticket 4: updateProfile now validates and persists dynamic database-backed
 * field values using the same field definitions as the onboarding profile step.
 * The split-card UX is unchanged — each section still saves independently.
 */
class ContributorSettingsController extends Controller
{
    public function __construct(
        private readonly ContributorProfileService            $profileService,
        private readonly ContributorOnboardingService         $onboardingService,
        private readonly ContributorProfileFieldConfigService $profileFieldConfigService,
        private readonly DynamicFieldValidator                $dynamicFieldValidator,
    )
    {
        parent::__construct();
    }

    // ── Avatar ────────────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/contributor/avatar
     *
     * Expects multipart/form-data with field: avatar (image file)
     * Returns: { url: string }
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $file = $request->file('avatar');

        if (!$file) {
            return $this->errorResponse('No avatar file provided.', 400);
        }

        try {
            $url = $this->profileService->uploadAvatar(
                userId: $userId,
                siteId: SiteContext::getId(),
                file: $file,
            );

            return $this->resourceResponse(['url' => $url]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('Avatar upload failed. Please try again.', 500);
        }
    }

    /**
     * DELETE /api/{site}/open-collab/contributor/avatar
     */
    public function removeAvatar(): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        try {
            $this->profileService->removeAvatar(
                userId: $userId,
                siteId: SiteContext::getId(),
            );

            return $this->successResponse('Avatar removed.');
        } catch (Exception $e) {
            return $this->errorResponse('Could not remove avatar. Please try again.', 500);
        }
    }

    // ── Expertise ─────────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/contributor/expertise
     *
     * Body: { expertise: string[] }
     */
    public function saveExpertise(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $raw = $request->input('expertise');

        if (!is_array($raw)) {
            return $this->errorResponse('expertise must be an array of strings.', 422);
        }

        try {
            $profile = $this->profileService->saveExpertise(
                userId: $userId,
                siteId: SiteContext::getId(),
                tags: $raw,
            );

            return $this->jsonResponse([
                'expertise' => $profile->expertise_array,
                'message' => 'Expertise saved.',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('Could not save expertise. Please try again.', 500);
        }
    }

    // ── Profile (core fields + dynamic fields) ────────────────────────────────

    /**
     * POST /api/{site}/open-collab/onboarding/profile
     * POST /api/{site}/open-collab/contributor/profile   (alias)
     *
     * Ticket 4: validates and persists dynamic database-backed field values
     * alongside core profile fields. Both paths use the same field definitions
     * as the onboarding profile step.
     *
     * Body (JSON): any mix of core field keys + dynamic field keys.
     * The full submitted payload is passed to ContributorProfileService, which
     * resolves which keys map to profile columns and persists them.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $siteId = SiteContext::getId();
        $site = Site::find($siteId);

        if (!$site) {
            return $this->errorResponse('Site not found.', 404);
        }

        $rawInput = $request->all();

        // Ticket 4: validate dynamic fields before persisting anything.
        $fieldDefinitions = $this->profileFieldConfigService->activeFieldsForSite($site);
        $validationErrors = $this->dynamicFieldValidator->validate($fieldDefinitions, $rawInput);

        if (!empty($validationErrors)) {
            return $this->errorResponse('Validation failed.', 422, $validationErrors);
        }

        try {
            $profile = $this->profileService->updateProfile(
                userId: $userId,
                siteId: $siteId,
                data: $rawInput,
            );

            $this->onboardingService->markProfileInProgress($userId, $siteId);

            return $this->jsonResponse([
                'profile' => (new ContributorProfileResource($profile))->toArray(),
                'message' => 'Profile updated.',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('Could not update profile. Please try again.', 500);
        }
    }
}