<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Subscriptions\PrintFulfillmentStatus;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Resource\ResourceCollection;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Resources\PrintFulfillmentResource;
use App\Search\SearchCriteriaParser;
use Exception;

/**
 * REST surface for PrintFulfillment records.
 *
 * Routes:
 *   GET  /print-fulfillments                    — paginated list (status, batch_id, issue_id, search)
 *   GET  /print-fulfillments/{fulfillment}       — detail with issue delivery and batch
 *   GET  /batches/{batch}/print-fulfillments     — list fulfillments for a specific batch
 *   PUT  /print-fulfillments/{fulfillment}/tracking — update tracking number (marks as shipped)
 *
 * All business state transitions live on the model. This controller is
 * deliberately thin and handles only HTTP concerns.
 */
class PrintFulfillmentController extends Controller
{
    public function __construct(
        private readonly PrintFulfillmentRepository $fulfillmentRepository,
        private readonly PrintBatchRepository       $batchRepository,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // GET /print-fulfillments
    // =========================================================================

    /**
     * Paginated list of PrintFulfillments.
     *
     * Query params:
     *   status      — filter by fulfillment status (pending|shipped|failed)
     *   batch_id    — filter by batch
     *   issue_id    — filter by issue delivery
     *   search      — partial match on full_name, postcode, or tracking_number
     *   per_page    — default 25, max 100
     */
    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->fulfillmentRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, PrintFulfillmentResource::class);
            return $this->resourceResponse($collection->toArray(), 200);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // GET /print-fulfillments/{fulfillment}
    // =========================================================================

    /**
     * Full detail for a single PrintFulfillment including the related
     * IssueDelivery and PrintBatch records.
     */
    public function show(int $fulfillmentId): JsonResponse
    {
        $fulfillment = $this->fulfillmentRepository->find($fulfillmentId, ['batch', 'subscription', 'batch.issueDelivery', 'issuesDelivered']);

        if (!$fulfillment) {
            return $this->errorResponse('Print fulfillment not found', 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'data' => PrintFulfillmentResource::make($fulfillment)->toArray(),
        ]);
    }

    // =========================================================================
    // GET /batches/{batch}/print-fulfillments
    // =========================================================================

    /**
     * List all PrintFulfillments belonging to a specific PrintBatch.
     *
     * Optional query param: ?status=pending|shipped|failed
     */
    public function listByBatch(Request $request, int $batchId): JsonResponse
    {
        $batch = $this->batchRepository->find($batchId);

        if (!$batch) {
            return $this->errorResponse('Print batch not found', 404);
        }

        $filters = $this->extractStatusFilter($request);
        $fulfillments = $this->fulfillmentRepository->listForBatch($batchId, $filters);

        $collection = new ResourceCollection($fulfillments->toArray(), PrintFulfillmentResource::class);

        return $this->resourceResponse($collection->toArray());
    }

    // =========================================================================
    // PUT /print-fulfillments/{fulfillment}/tracking
    // =========================================================================

    private function extractStatusFilter(Request $request): array
    {
        $status = $request->query('status');

        if ($status && !PrintFulfillmentStatus::tryFrom($status)) {
            return []; // silently ignore invalid status on sub-resource list endpoints
        }

        return $status ? ['status' => $status] : [];
    }

    // =========================================================================
    // Private
    // =========================================================================

    /**
     * Update the tracking number for a PrintFulfillment.
     *
     * Accepts a JSON body: { "tracking_number": "TRACK123456" }
     *
     * This also marks the fulfillment status as SHIPPED via the model method.
     * Returns 422 when tracking_number is missing or blank.
     */
    public function updateTracking(Request $request, int $fulfillmentId): JsonResponse
    {
        $fulfillment = $this->fulfillmentRepository->find($fulfillmentId, ['subscription', 'batch', 'batch.issueDelivery', 'issuesDelivered']);

        if (!$fulfillment) {
            return $this->errorResponse('Print fulfillment not found', 404);
        }

        $trackingNumber = trim((string)$request->input('tracking_number', ''));

        if ($trackingNumber === '') {
            return $this->errorResponse('tracking_number is required and must not be blank', 422);
        }

        $fulfillment->updateTrackingNumber($trackingNumber);

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Tracking number updated',
            'data' => PrintFulfillmentResource::make(
                $fulfillment->fresh(['batch', 'batch.issueDelivery', 'issuesDelivered', 'subscription'])
            )->toArray(),
        ]);
    }
}