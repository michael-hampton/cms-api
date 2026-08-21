<?php

declare(strict_types=1);

namespace App\Controllers\Subscription;

use App\Controllers\Concerns\RequiresSitePermission;
use App\Controllers\Controller;
use App\Enums\Subscriptions\PrintBatchStatus;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Resource\ResourceCollection;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Resources\PrintBatchResource;
use App\Services\Subscriptions\Printing\PrintBatchDownloadService;
use App\Services\Subscriptions\Printing\PrintBatchExportTriggerService;

/**
 * REST surface for PrintBatch export reports.
 *
 * Routes:
 *   GET  /print-batches                   — paginated list (status, issue_id, territory_id, from, to)
 *   GET  /print-batches/{printBatch}       — detail, including live file existence/size
 *   POST /print-batches/{printBatch}/export — (re-)trigger export (only when not exported/exporting)
 *   GET  /print-batches/{printBatch}/download — stream the exported file
 *
 * Deliberately thin, matching PrintRunController: state validation lives on
 * the PrintBatch model, the dispatch workflow lives in
 * PrintBatchExportTriggerService, and file resolution lives in
 * PrintBatchDownloadService (both isolate infrastructure — the queue and
 * the export transport — from this controller).
 */
class PrintBatchReportController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly PrintBatchRepository          $printBatchRepository,
        private readonly PrintBatchExportTriggerService $triggerService,
        private readonly PrintBatchDownloadService      $downloadService,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // GET /print-batches
    // =========================================================================

    /**
     * Query params: status, issue_id, territory_id, from (Y-m-d), to (Y-m-d), per_page (default 25)
     */
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.view')) {
            return $response;
        }

        $filters = array_filter([
            'status' => $request->query('status'),
            'issue_delivery_id' => $request->query('issue_id'),
            'territory_id' => $request->query('territory_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'updated_from' => $request->query('updated_from'),
            'updated_to' => $request->query('updated_to'),
        ]);

        if (isset($filters['status']) && !PrintBatchStatus::tryFrom($filters['status'])) {
            return $this->errorResponse('Invalid status value', 422);
        }

        $perPage = min((int)($request->query('per_page') ?? 25), 100);
        $paginated = $this->printBatchRepository->search($filters, $perPage);

        $collection = new ResourceCollection($paginated['data']->toArray(), PrintBatchResource::class);

        return $this->resourceResponse(['pagination' => $paginated['pagination'], ...$collection->toArray()]);
    }

    // =========================================================================
    // GET /print-batches/{printBatch}
    // =========================================================================

    public function show(int $printBatchId): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.view')) {
            return $response;
        }

        $batch = $this->printBatchRepository->find($printBatchId);

        if (!$batch) {
            return $this->errorResponse('Print batch not found', 404);
        }

        $data = PrintBatchResource::make($batch)->toArray();
        $data['file_exists'] = $this->downloadService->fileExists($batch);
        $data['file_size_bytes'] = $this->downloadService->fileSize($batch);

        return $this->resourceResponse([
            'success' => true,
            'data' => $data,
        ]);
    }

    // =========================================================================
    // POST /print-batches/{printBatch}/export
    // =========================================================================

    public function trigger(int $printBatchId): JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.schedule')) {
            return $response;
        }

        $batch = $this->printBatchRepository->find($printBatchId);

        if (!$batch) {
            return $this->errorResponse('Print batch not found', 404);
        }

        if (!$batch->canTriggerExport()) {
            return $this->errorResponse(
                "Print batch cannot be exported in its current status: {$batch->status}",
                422
            );
        }

        $this->triggerService->trigger($batch);

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Print batch export queued',
            'data' => PrintBatchResource::make($batch->fresh())->toArray(),
        ]);
    }

    // =========================================================================
    // GET /print-batches/{printBatch}/download
    // =========================================================================

    public function download(int $printBatchId): Response|JsonResponse
    {
        if ($response = $this->requireSitePermission('issues.view')) {
            return $response;
        }

        $batch = $this->printBatchRepository->find($printBatchId);

        if (!$batch) {
            return $this->errorResponse('Print batch not found', 404);
        }

        if (!$batch->isExported() || !$this->downloadService->fileExists($batch)) {
            return $this->errorResponse('Print batch file is not available for download', 404);
        }

        $file = $this->downloadService->download($batch);

        return new Response($file->contents, 200, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => 'attachment; filename="' . $file->filename . '"',
            'Content-Length' => (string)strlen($file->contents),
        ]);
    }
}
