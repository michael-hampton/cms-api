<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Subscriptions\PrintRunStatus;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\ResourceCollection;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Resources\PrintRunResource;

/**
 * REST surface for PrintRun records.
 *
 * Routes:
 *   GET  /issues/{issue}/print-runs          — list by issue, filterable by status
 *   GET  /print-runs                         — paginated list (status, issue_id, date)
 *   GET  /print-runs/{printRun}              — detail
 *   PUT  /print-runs/{printRun}/cancel       — cancel (only when pending)
 *
 * This controller is deliberately thin: all business rules live in the
 * repository and the PrintRun model. No service is needed for these read
 * and single-model-state-transition operations.
 */
class PrintRunController extends Controller
{
    public function __construct(
        private readonly PrintRunRepository      $printRunRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // GET /issues/{issue}/print-runs
    // =========================================================================

    /**
     * List all PrintRuns for a given IssueDelivery.
     *
     * Optional query param: ?status=pending|complete|cancelled|failed
     */
    public function listByIssue(Request $request, int $issueId): JsonResponse
    {
        $issueDelivery = $this->issueDeliveryRepository->find($issueId);

        if (!$issueDelivery) {
            return $this->errorResponse('Issue delivery not found', 404);
        }

        $filters = $this->extractStatusFilter($request);
        $printRuns = $this->printRunRepository->listForIssueDelivery($issueId, $filters);

        $collection = new ResourceCollection($printRuns->toArray(), PrintRunResource::class);

        return $this->resourceResponse($collection->toArray());
    }

    // =========================================================================
    // GET /print-runs
    // =========================================================================

    private function extractStatusFilter(Request $request): array
    {
        $status = $request->query('status');

        if ($status && !PrintRunStatus::tryFrom($status)) {
            return []; // silently ignore invalid status on list-by-issue
        }

        return $status ? ['status' => $status] : [];
    }

    // =========================================================================
    // GET /print-runs/{printRun}
    // =========================================================================

    /**
     * Paginated list of PrintRuns across all issues.
     *
     * Query params: status, issue_id, date (Y-m-d), per_page (default 25)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'status' => $request->query('status'),
            'issue_delivery_id' => $request->query('issue_id'),
            'date' => $request->query('date'),
        ]);

        if (isset($filters['status']) && !PrintRunStatus::tryFrom($filters['status'])) {
            return $this->errorResponse('Invalid status value', 422);
        }

        $perPage = min((int)($request->query('per_page') ?? 25), 100);
        $paginated = $this->printRunRepository->search($filters, $perPage);

        $collection = new ResourceCollection($paginated['data']->toArray(), PrintRunResource::class);

        return $this->resourceResponse(['pagination' => $paginated['pagination'], ...$collection->toArray()]);
    }

    // =========================================================================
    // PUT /print-runs/{printRun}/cancel
    // =========================================================================

    public function show(int $printRunId): JsonResponse
    {
        $printRun = $this->printRunRepository->find($printRunId);

        if (!$printRun) {
            return $this->errorResponse('Print run not found', 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'data' => PrintRunResource::make($printRun)->toArray()
        ]);
    }

    // =========================================================================
    // Private
    // =========================================================================

    /**
     * Cancel a pending PrintRun.
     *
     * Returns 422 when the PrintRun is not in a cancellable state (i.e. not
     * pending). This is a domain rule, not a validation error, but 422 keeps
     * the client contract consistent with other status-transition endpoints.
     */
    public function cancel(int $printRunId): JsonResponse
    {
        $printRun = $this->printRunRepository->find($printRunId);

        if (!$printRun) {
            return $this->errorResponse('Print run not found', 404);
        }

        if (!$printRun->canCancel()) {
            return $this->errorResponse(
                "Print run cannot be cancelled in its current status: {$printRun->status}",
                422
            );
        }

        $printRun->markCancelled();

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Print run cancelled',
            'data' => PrintRunResource::make($printRun->fresh())->toArray(),
        ]);
    }
}