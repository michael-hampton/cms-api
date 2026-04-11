<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Requests\OpenCollab\CloseContributorAccountRequest;
use App\Services\OpenCollab\ContributorTerminationService;

/**
 * Handles contributor self-service account closure.
 *
 * Account closure is immediate — it calls ContributorTerminationService which:
 *   1. Deactivates the account (is_active = false)
 *   2. Revokes site access
 *   3. Cancels in-flight payouts
 *   4. Archives unpublished pages
 *   5. Fires ContributorAccountClosedEvent (notifies admin, etc.)
 *
 * This is intentionally irreversible through the normal UI.
 *
 * Routes:
 *   POST /api/{site}/open-collab/contributor/close-account
 */
class ContributorAccountController extends Controller
{
    public function __construct(
        private readonly ContributorTerminationService $terminationService,
        private readonly Logger                        $logger,
    )
    {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/contributor/close-account
     *
     * Immediately closes the authenticated contributor's account.
     * Uses ContributorTerminationService so all side-effects are handled
     * consistently regardless of whether closure is self-service or admin-initiated.
     */
    public function requestClosure(CloseContributorAccountRequest $request): JsonResponse
    {
        $userId = Auth::id();

        if (!$userId) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        try {
            $data = $request->validated();

            $this->terminationService->close(
                userId: $userId,
                siteId: SiteContext::getId(),
                adminId: $userId, // self-initiated: the actor is the contributor themselves
                reason: $data['reason'],
            );

            return $this->successResponse(
                'Your account has been closed. Thank you for contributing.'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $this->logger->error('Contributor self-service account closure failed.', [
                'user_id' => $userId,
                'site_id' => SiteContext::getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Could not process your request. Please try again or contact support.',
                500
            );
        }
    }
}