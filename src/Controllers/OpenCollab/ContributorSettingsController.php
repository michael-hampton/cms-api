<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Resources\OpenCollab\ContributorProfileResource;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\ContributorProfileService;
use Exception;

/**
 * Self-service settings endpoints consumed by the settings/index view.
 *
 * Routes (all under /api/{site}/open-collab/contributor/):
 *   POST  avatar            — upload / replace profile photo
 *   DELETE avatar           — remove profile photo
 *   POST  expertise         — save expertise tag list
 *   POST  profile           — update bio (+ apply avatar URL from a prior upload)
 *
 * The onboarding/profile route is also kept here as an alias because the
 * settings view posts to /api/{site}/open-collab/onboarding/profile.
 */
class ContributorSettingsController extends Controller
{
    public function __construct(
        private readonly ContributorProfileService $profileService,
        private readonly ContributorOnboardingService $onboardingService,
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
     *
     * Removes the current avatar, deletes the file from disk.
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
     * Returns: { expertise: string[], message: string }
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
            echo $e->getMessage();
            die;
            return $this->errorResponse('Could not save expertise. Please try again.', 500);
        }
    }

    // ── Profile (bio + avatar URL) ────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/onboarding/profile
     * POST /api/{site}/open-collab/contributor/profile   (alias)
     *
     * Body (JSON):
     *   bio    string|null
     *   avatar string|null|''   — URL from a prior uploadAvatar() call,
     *                             '' / null means remove existing avatar
     * Returns: { profile: object, message: string }
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        // Fallback for frameworks where only() keeps keys regardless of presence
        $rawInput = $request->all();
        $filtered = [];
        foreach (['bio', 'avatar'] as $key) {
            if (array_key_exists($key, $rawInput)) {
                $filtered[$key] = $rawInput[$key];
            }
        }

        try {
            $profile = $this->profileService->updateProfile(
                userId: $userId,
                siteId: SiteContext::getId(),
                data: $filtered,
            );
            $this->onboardingService->markProfileInProgress($userId, SiteContext::getId());

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
