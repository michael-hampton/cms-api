<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Enums\OpenCollab\RejectionReason;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Requests\OpenCollab\RejectArticleRequest;
use App\Resources\OpenCollab\ContributorPageResource;
use App\Services\OpenCollab\ArticleApprovalService;

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
    public function __construct(
        private readonly ArticleApprovalService $approvalService,
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
        try {
            $page = $this->approvalService->approve($id, Auth::id());

            return $this->jsonResponse([
                'page' => (new ContributorPageResource($page))->toArray(),
                'message' => 'Article approved and published.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/articles/{id}/reject
     */
    public function reject(RejectArticleRequest $request, int $id): JsonResponse
    {
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
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/pages/{id}/submit
     * Contributor submits their article for review.
     */
    public function submit(int $id): JsonResponse
    {
        try {
            $page = $this->approvalService->submitForReview($id, Auth::id());

            return $this->jsonResponse([
                'page' => (new ContributorPageResource($page))->toArray(),
                'message' => 'Article submitted for review.',
            ]);
        } catch (UnauthorisedPageAccessException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/pages/{id}/resubmit
     * Contributor resubmits after rejection.
     */
    public function resubmit(int $id): JsonResponse
    {
        try {
            $page = $this->approvalService->resubmit($id, Auth::id());

            return $this->jsonResponse([
                'page' => (new ContributorPageResource($page))->toArray(),
                'message' => 'Article resubmitted for review.',
            ]);
        } catch (UnauthorisedPageAccessException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}