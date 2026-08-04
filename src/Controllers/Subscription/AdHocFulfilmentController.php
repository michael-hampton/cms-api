<?php

declare(strict_types=1);

namespace App\Controllers\Subscription;

use App\Controllers\Concerns\RequiresSitePermission;
use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\ResourceCollection;
use App\Repositories\Subscriptions\AdHocFulfilmentRequestRepository;
use App\Resources\AdHocFulfilmentRequestResource;
use App\Services\Subscriptions\Printing\AdHocFulfilmentGenerationService;
use InvalidArgumentException;
use RuntimeException;

/**
 * REST surface for manually (ad-hoc) triggered fulfilment file generation.
 *
 * Routes:
 *   GET  /ad-hoc-fulfilment-requests                   — paginated list of past requests
 *   GET  /ad-hoc-fulfilment-requests/{request}          — single request detail
 *   POST /ad-hoc-fulfilment-requests/print-batches/{printBatchId} — request generation for a PrintBatch
 *
 * Deliberately thin: eligibility/state validation and the dispatch workflow
 * both live in AdHocFulfilmentGenerationService; this controller's only
 * jobs are permission checks, request parsing, and resolving the current
 * user as the requester.
 */
class AdHocFulfilmentController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly AdHocFulfilmentRequestRepository $requestRepository,
        private readonly AdHocFulfilmentGenerationService $generationService,
    ) {
        parent::__construct();
    }

    // =========================================================================
    // GET /ad-hoc-fulfilment-requests
    // =========================================================================

    /**
     * Query params: process, requested_by_user_id, from (Y-m-d), to (Y-m-d), per_page (default 25)
     */
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->requireSitePermission('fulfilment.ad_hoc.view')) {
            return $response;
        }

        $filters = array_filter([
            'process' => $request->query('process'),
            'requested_by_user_id' => $request->query('requested_by_user_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);

        $perPage = min((int)($request->query('per_page') ?? 25), 100);
        $paginated = $this->requestRepository->search($filters, $perPage);

        $collection = new ResourceCollection($paginated['data']->toArray(), AdHocFulfilmentRequestResource::class);

        return $this->resourceResponse(['pagination' => $paginated['pagination'], ...$collection->toArray()]);
    }

    // =========================================================================
    // GET /ad-hoc-fulfilment-requests/{request}
    // =========================================================================

    public function show(int $requestId): JsonResponse
    {
        if ($response = $this->requireSitePermission('fulfilment.ad_hoc.view')) {
            return $response;
        }

        $adHocRequest = $this->requestRepository->find($requestId);

        if (!$adHocRequest) {
            return $this->errorResponse('Ad-hoc fulfilment request not found', 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'data' => AdHocFulfilmentRequestResource::make($adHocRequest)->toArray(),
        ]);
    }

    // =========================================================================
    // POST /ad-hoc-fulfilment-requests/print-batches/{printBatchId}
    // =========================================================================

    public function generateForPrintBatch(int $printBatchId): JsonResponse
    {
        if ($response = $this->requireSitePermission('fulfilment.ad_hoc.generate')) {
            return $response;
        }

        $userId = Auth::id();

        if (!$userId) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $adHocRequest = $this->generationService->generateForPrintBatch($printBatchId, (int)$userId);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Fulfilment file generation queued',
            'data' => AdHocFulfilmentRequestResource::make($adHocRequest)->toArray(),
        ]);
    }
}