<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Enums\OpenCollab\RejectionReason;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Requests\OpenCollab\RejectArticleRequest;
use App\Resources\OpenCollab\ContributorPageResource;
use App\Services\OpenCollab\ArticleApprovalService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use InvalidArgumentException;

/**
 * Admin-side article moderation.
 *
 * Routes:
 *   GET  /api/{site}/open-collab/admin/articles/pending      — review queue
 *   POST /api/{site}/open-collab/admin/articles/{id}/approve — approve
 *   POST /api/{site}/open-collab/admin/articles/{id}/reject  — reject
 *
 * Contributor-side:
 *   POST /api/{site}/open-collab/pages/{id}/submit           — submit for review
 *   POST /api/{site}/open-collab/pages/{id}/resubmit         — resubmit after rejection
 */
class ArticleApprovalController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly ArticleApprovalService         $approvalService,
        private readonly OpenCollabAuthorizationService $authorization,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/admin/articles/pending
     * Admin review queue — articles awaiting approval.
     */
    public function pending(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.review', 'pages.approve', 'pages.reject', 'content.review', 'content.approve', 'content.reject'])) {
            return $response;
        }

        $articles = $this->approvalService->pendingReviewForSite(SiteContext::getId());

        return $this->resourceResponse(
            $articles->map(fn($page) => (new ContributorPageResource($page))->toArray())->toArray()
        );
    }

    /**
     * POST /api/{site}/open-collab/admin/articles/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.approve', 'content.approve'])) {
            return $response;
        }

        try {
            $page = $this->approvalService->approve($id, Auth::id());

            return $this->jsonResponse([
                'page' => (new ContributorPageResource($page))->toArray(),
                'message' => 'Article approved and published.',
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/articles/{id}/reject
     */
    public function reject(RejectArticleRequest $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.reject', 'content.reject'])) {
            return $response;
        }

        try {
            $data = $request->validated();
            $reason = RejectionReason::from($data['reason']);

            $page = $this->approvalService->reject(
                pageId: $id,
                adminId: Auth::id(),
                reason: $reason,
                notes: $data['notes'] ?? null,
            );

            return $this->jsonResponse([
                'page' => (new ContributorPageResource($page))->toArray(),
                'message' => 'Article rejected.',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/pages/{id}/submit
     * Contributor submits their article for review.
     */
    public function submit(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.submit_for_approval', 'content.submit'])) {
            return $response;
        }

        try {
            $page = $this->approvalService->submitForReview($id, Auth::id());

            return $this->jsonResponse([
                'page' => (new ContributorPageResource($page))->toArray(),
                'message' => 'Article submitted for review.',
            ]);
        } catch (OnboardingIncompleteException $e) {
            return $this->errorResponse($e->getMessage(), 403, [
                'pending_steps' => $e->getPendingSteps(),
                'redirect' => '/contributor/onboarding',
            ]);
        } catch (UnauthorisedPageAccessException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/pages/{id}/resubmit
     * Contributor resubmits after rejection.
     */
    public function resubmit(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.submit_for_approval', 'content.submit'])) {
            return $response;
        }

        try {
            $page = $this->approvalService->resubmit($id, Auth::id());

            return $this->jsonResponse([
                'page' => (new ContributorPageResource($page))->toArray(),
                'message' => 'Article resubmitted for review.',
            ]);
        } catch (OnboardingIncompleteException $e) {
            return $this->errorResponse($e->getMessage(), 403, [
                'pending_steps' => $e->getPendingSteps(),
                'redirect' => '/contributor/onboarding',
            ]);
        } catch (UnauthorisedPageAccessException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
