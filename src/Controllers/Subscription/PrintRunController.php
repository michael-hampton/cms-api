<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Controllers\Concerns\RequiresSitePermission;
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
 *   PUT  /print-runs/{printRun}/retry        — retry (only when failed or cancelled)
 *   POST /print-runs/bulk-cancel             — cancel up to 100 runs by ID
 *
 * This controller is deliberately thin: all business rules live in the
 * repository and the PrintRun model. No service is needed for these read
 * and single-model-state-transition operations.
 */
class PrintRunController extends Controller
{
    use RequiresSitePermission;

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
        if ($response = $this->requireSitePermission('issues.view')) {
            return $response;
        }

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

    /**
     * Paginated list of PrintRuns across all issues.
     *
     * Query params: status, issue_id, date (Y-m-d), per_page (default 25)
     */
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.view')) {
            return $response;
        }

        $filters = array_filter([
            'status' => $request->query('status'),
            'issue_delivery_id' => $request->query('issue_id'),
            'date' => $request->query('date'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'updated_from' => $request->query('updated_from'),
            'updated_to' => $request->query('updated_to'),
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
    // GET /print-runs/{printRun}
    // =========================================================================

    public function show(int $printRunId): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.view')) {
            return $response;
        }

        $printRun = $this->printRunRepository->find($printRunId);

        if (!$printRun) {
            return $this->errorResponse('Print run not found', 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'data' => PrintRunResource::make($printRun)->toArray(),
        ]);
    }

    // =========================================================================
    // PUT /print-runs/{printRun}/cancel
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
        if ($response = $this->requireSitePermission('issues.schedule')) {
            return $response;
        }

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

    // =========================================================================
    // PUT /print-runs/{printRun}/retry
    // =========================================================================

    /**
     * Retry a failed or cancelled PrintRun.
     *
     * Resets status to pending and clears all chunk-tracking counters so the
     * run can be dispatched cleanly from the beginning.
     *
     * Returns 422 when the PrintRun is not in a retryable state.
     */
    public function retry(int $printRunId): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.schedule')) {
            return $response;
        }

        $printRun = $this->printRunRepository->find($printRunId);

        if (!$printRun) {
            return $this->errorResponse('Print run not found', 404);
        }

        if (!$printRun->canRetry()) {
            return $this->errorResponse(
                "Print run cannot be retried in its current status: {$printRun->status}",
                422
            );
        }

        $printRun->markRetry();

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Print run reset to pending',
            'data' => PrintRunResource::make($printRun->fresh())->toArray(),
        ]);
    }

    // =========================================================================
    // POST /print-runs/bulk-cancel
    // =========================================================================

    /**
     * Cancel multiple PrintRuns by ID in a single request.
     *
     * Accepts a JSON body: { "print_run_ids": [1, 2, 3, ...] }
     * Maximum 100 IDs per request.
     *
     * Processes valid, cancellable runs and reports on anything skipped:
     *   - "not_found"      — IDs that don't exist in the database
     *   - "not_cancellable" — runs that exist but are in a non-cancellable state
     *
     * Always returns 200 with a structured summary. The caller must inspect
     * the summary to determine whether all requested runs were cancelled.
     */
    public function bulkCancel(Request $request): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.schedule')) {
            return $response;
        }

        $ids = $request->input('print_run_ids', []);

        if (!is_array($ids) || empty($ids)) {
            return $this->errorResponse('print_run_ids must be a non-empty array', 422);
        }

        if (count($ids) > 100) {
            return $this->errorResponse('A maximum of 100 print run IDs may be submitted per request', 422);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        $printRuns = $this->printRunRepository->findMany($ids);
        $foundIds = $printRuns->pluck('id')->all();
        $notFoundIds = array_values(array_diff($ids, $foundIds));

        $cancelled = [];
        $notCancellable = [];

        foreach ($printRuns as $printRun) {
            if ($printRun->canCancel()) {
                $printRun->markCancelled();
                $cancelled[] = $printRun->id;
            } else {
                $notCancellable[] = [
                    'id' => $printRun->id,
                    'status' => $printRun->status,
                ];
            }
        }

        return $this->resourceResponse([
            'success' => true,
            'summary' => [
                'cancelled' => count($cancelled),
                'not_found' => count($notFoundIds),
                'not_cancellable' => count($notCancellable),
            ],
            'cancelled' => $cancelled,
            'not_found' => $notFoundIds,
            'not_cancellable' => $notCancellable,
        ]);
    }

    // =========================================================================
    // Private
    // =========================================================================

    private function extractStatusFilter(Request $request): array
    {
        $status = $request->query('status');

        if ($status && !PrintRunStatus::tryFrom($status)) {
            return []; // silently ignore invalid status on list-by-issue
        }

        return $status ? ['status' => $status] : [];
    }
}
