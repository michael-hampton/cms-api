<?php

namespace App\Controllers\Cms;

use App\Controllers\Controller;
use App\Exceptions\OpenCollab\BriefAssignmentRequestAlreadyResolvedException;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Services\Cms\BriefAssignmentRequestService;
use App\Services\Cms\BriefService;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class BriefAssignmentRequestController extends Controller
{
    public function __construct(
        private readonly BriefAssignmentRequestService $requestService,
        private readonly BriefService                  $briefService,
    ) {
        parent::__construct();
    }

    /**
     * GET /cms/briefs/{briefId}/requests
     * List all requests for a brief (CMS editor view — all fields).
     */
    public function index(int $briefId, string $site): JsonResponse
    {
        try {
            $brief = $this->briefService->getCompleteBrief($briefId);

            if (!$brief) {
                return $this->errorResponse('Brief not found', 404);
            }

            $requests = $this->requestService->getAllRequestsForBrief($briefId);

            return $this->resourceResponse(['items' => $requests->map(fn($r) => $r->toArray())->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /cms/briefs/{briefId}/requests/{requestId}/approve
     * Approve a deadline change or negotiation request.
     */
    public function approve(int $briefId, int $requestId, Request $request, string $site): JsonResponse
    {
        try {
            $editorId       = (int) ($request->get('user_id') ?? Auth::id());
            $editorResponse = $request->get('editor_response');

            $assignmentRequest = $this->requestService->findRequestById($requestId);

            if (!$assignmentRequest || (int) $assignmentRequest->brief_id !== $briefId) {
                return $this->errorResponse('Request not found', 404);
            }

            $resolved = match ($assignmentRequest->type) {
                'deadline_change' => $this->requestService->approveDeadlineChangeRequest(
                    $assignmentRequest, $editorId, $editorResponse,
                ),
                'negotiation' => $this->requestService->approveNegotiationRequest(
                    $assignmentRequest, $editorId, $editorResponse,
                ),
                default => throw new InvalidArgumentException(
                    "Request type '{$assignmentRequest->type}' cannot be approved via this endpoint.",
                ),
            };

            return $this->resourceResponse(['data' => $resolved->toArray()]);
        } catch (BriefAssignmentRequestAlreadyResolvedException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /cms/briefs/{briefId}/requests/{requestId}/reject
     * Reject a deadline change or negotiation request.
     */
    public function reject(int $briefId, int $requestId, Request $request, string $site): JsonResponse
    {
        try {
            $editorId       = (int) ($request->get('user_id') ?? Auth::id());
            $editorResponse = $request->get('editor_response');

            $assignmentRequest = $this->requestService->findRequestById($requestId);

            if (!$assignmentRequest || (int) $assignmentRequest->brief_id !== $briefId) {
                return $this->errorResponse('Request not found', 404);
            }

            $resolved = match ($assignmentRequest->type) {
                'deadline_change' => $this->requestService->rejectDeadlineChangeRequest(
                    $assignmentRequest, $editorId, $editorResponse,
                ),
                'negotiation' => $this->requestService->rejectNegotiationRequest(
                    $assignmentRequest, $editorId, $editorResponse,
                ),
                default => throw new InvalidArgumentException(
                    "Request type '{$assignmentRequest->type}' cannot be rejected via this endpoint.",
                ),
            };

            return $this->resourceResponse(['data' => $resolved->toArray()]);
        } catch (BriefAssignmentRequestAlreadyResolvedException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /cms/briefs/{briefId}/requests/{requestId}/respond
     * Respond to a clarification request.
     */
    public function respond(int $briefId, int $requestId, Request $request, string $site): JsonResponse
    {
        try {
            $editorId       = (int) ($request->get('user_id') ?? Auth::id());
            $editorResponse = (string) $request->get('editor_response', '');

            $assignmentRequest = $this->requestService->findRequestById($requestId);

            if (!$assignmentRequest || (int) $assignmentRequest->brief_id !== $briefId) {
                return $this->errorResponse('Request not found', 404);
            }

            $resolved = $this->requestService->respondToClarificationRequest(
                $assignmentRequest,
                $editorId,
                $editorResponse,
            );

            return $this->resourceResponse(['data' => $resolved->toArray()]);
        } catch (BriefAssignmentRequestAlreadyResolvedException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /cms/briefs/{briefId}/requests/{requestId}/cancel
     * Cancel a pending request.
     */
    public function cancel(int $briefId, int $requestId, Request $request, string $site): JsonResponse
    {
        try {
            $actorId = (int) ($request->get('user_id') ?? Auth::id());

            $assignmentRequest = $this->requestService->findRequestById($requestId);

            if (!$assignmentRequest || (int) $assignmentRequest->brief_id !== $briefId) {
                return $this->errorResponse('Request not found', 404);
            }

            $cancelled = $this->requestService->cancelRequest($assignmentRequest, $actorId);

            return $this->resourceResponse(['data' => $cancelled->toArray()]);
        } catch (BriefAssignmentRequestAlreadyResolvedException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}