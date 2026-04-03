<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Repositories\Workflow\WorkflowRunRepository;
use App\Resources\WorkflowRunResource;
use App\Search\SearchCriteriaParser;
use Exception;

/**
 * REST surface for WorkflowRun records.
 *
 * Routes:
 *   GET  /workflow-runs              — paginated list (status, workflow_type, search, date range)
 *   GET  /workflow-runs/{run}        — single run detail
 *
 * All reads. WorkflowRuns are written exclusively by the workflow engine,
 * not by HTTP clients, so there are no mutating endpoints here.
 */
class WorkflowRunController extends Controller
{
    public function __construct(
        private readonly WorkflowRunRepository $runRepository,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // GET /workflow-runs
    // =========================================================================

    /**
     * Paginated list of WorkflowRuns.
     *
     * Query params:
     *   status         — filter by WorkflowRunStatus value
     *   workflow_type  — partial match on the fully-qualified class name
     *   search         — alias for workflow_type partial match (for search-box parity)
     *   started_after  — ISO-8601 date string lower bound on started_at
     *   started_before — ISO-8601 date string upper bound on started_at
     *   per_page       — default 25, max 100
     */
    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->runRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, WorkflowRunResource::class);
            return $this->resourceResponse($collection->toArray(), 200);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // GET /workflow-runs/{run}
    // =========================================================================

    /**
     * Full detail for a single WorkflowRun.
     *
     * Returns the complete input payload, phase-by-phase summary, and any
     * error message so the frontend detail modal can render everything.
     */
    public function show(int $runId): JsonResponse
    {
        $run = $this->runRepository->find($runId);

        if (!$run) {
            return $this->errorResponse('Workflow run not found', 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'data' => WorkflowRunResource::make($run)->toArray(),
        ]);
    }

    // =========================================================================
    // Private
    // =========================================================================

    private function extractStatusFilter(Request $request): ?WorkflowRunStatus
    {
        $status = $request->query('status');

        if (!$status) {
            return null;
        }

        $resolved = WorkflowRunStatus::tryFrom($status);

        // Silently ignore unrecognised values — same pattern as PrintFulfillmentController
        return $resolved;
    }
}