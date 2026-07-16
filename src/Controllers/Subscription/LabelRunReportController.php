<?php

declare(strict_types=1);

namespace App\Controllers\Subscription;

use App\Controllers\Concerns\RequiresSitePermission;
use App\Controllers\Controller;
use App\Enums\Subscriptions\LabelRunStatus;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Resource\ResourceCollection;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Resources\LabelRunResource;
use App\Services\Subscriptions\Printing\Label\LabelRunDownloadService;
use App\Services\Subscriptions\Printing\Label\LabelRunTriggerService;

/**
 * REST surface for LabelRun generation reports.
 *
 * Routes:
 *   GET  /label-runs                     — paginated list (status, print_batch_id, subscription_id, from, to)
 *   GET  /label-runs/{labelRun}           — detail, including live file existence/size
 *   POST /label-runs/{labelRun}/generate  — (re-)trigger generation (only when pending or retryable-failed)
 *   GET  /label-runs/{labelRun}/download  — stream the generated file
 *
 * Deliberately thin, matching PrintRunController: state validation lives on
 * the LabelRun model, the dispatch workflow lives in LabelRunTriggerService,
 * and file resolution lives in LabelRunDownloadService.
 */
class LabelRunReportController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly LabelRunRepository       $labelRunRepository,
        private readonly LabelRunTriggerService   $triggerService,
        private readonly LabelRunDownloadService  $downloadService,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // GET /label-runs
    // =========================================================================

    /**
     * Query params: status, print_batch_id, subscription_id, from (Y-m-d), to (Y-m-d), per_page (default 25)
     */
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.view')) {
            return $response;
        }

        $filters = array_filter([
            'status' => $request->query('status'),
            'print_batch_id' => $request->query('print_batch_id'),
            'subscription_id' => $request->query('subscription_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);

        if (isset($filters['status']) && !LabelRunStatus::tryFrom($filters['status'])) {
            return $this->errorResponse('Invalid status value', 422);
        }

        $perPage = min((int)($request->query('per_page') ?? 25), 100);
        $paginated = $this->labelRunRepository->search($filters, $perPage);

        $collection = new ResourceCollection($paginated['data']->toArray(), LabelRunResource::class);

        return $this->resourceResponse(['pagination' => $paginated['pagination'], ...$collection->toArray()]);
    }

    // =========================================================================
    // GET /label-runs/{labelRun}
    // =========================================================================

    public function show(int $labelRunId): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.view')) {
            return $response;
        }

        $labelRun = $this->labelRunRepository->find($labelRunId);

        if (!$labelRun) {
            return $this->errorResponse('Label run not found', 404);
        }

        $data = LabelRunResource::make($labelRun)->toArray();
        $data['file_exists'] = $this->downloadService->fileExists($labelRun);
        $data['file_size_bytes'] = $this->downloadService->fileSize($labelRun);

        return $this->resourceResponse([
            'success' => true,
            'data' => $data,
        ]);
    }

    // =========================================================================
    // POST /label-runs/{labelRun}/generate
    // =========================================================================

    public function trigger(int $labelRunId): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.schedule')) {
            return $response;
        }

        $labelRun = $this->labelRunRepository->find($labelRunId);

        if (!$labelRun) {
            return $this->errorResponse('Label run not found', 404);
        }

        if (!$labelRun->canTriggerGeneration()) {
            return $this->errorResponse(
                "Label run cannot be generated in its current status: {$labelRun->status}",
                422
            );
        }

        $this->triggerService->trigger($labelRun);

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Label generation queued',
            'data' => LabelRunResource::make($labelRun->fresh())->toArray(),
        ]);
    }

    // =========================================================================
    // GET /label-runs/{labelRun}/download
    // =========================================================================

    public function download(int $labelRunId): Response|JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.view')) {
            return $response;
        }

        $labelRun = $this->labelRunRepository->find($labelRunId);

        if (!$labelRun) {
            return $this->errorResponse('Label run not found', 404);
        }

        if (!$labelRun->isComplete() || !$this->downloadService->fileExists($labelRun)) {
            return $this->errorResponse('Label file is not available for download', 404);
        }

        $file = $this->downloadService->download($labelRun);

        return new Response($file->contents, 200, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => 'attachment; filename="' . $file->filename . '"',
            'Content-Length' => (string)strlen($file->contents),
        ]);
    }
}
