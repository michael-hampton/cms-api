<?php

namespace App\Controllers;

use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\PageRepository;
use App\Search\SearchCriteriaParser;
use Exception;

class PipelineController extends Controller
{
    public function __construct(
        private readonly PageRepository $pageRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);

            $results = $this->pageRepository->searchPipeline($criteria);

            return $this->jsonResponse([
                'stages' => $results,
                'success' => true
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateStage(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $status = $request->get('status');

            if (!$status) {
                return $this->errorResponse('Status is required', 422);
            }

            $validStatuses = ['draft', 'in-review', 'scheduled', 'published'];
            if (!in_array($status, $validStatuses)) {
                return $this->errorResponse('Invalid status', 422);
            }

            $success = $this->pageRepository->updatePageStatus($id, $status);

            if (!$success) {
                return $this->errorResponse('Page not found', 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Page status updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function metrics(Request $request, string $siteName): JsonResponse
    {
        try {
            $siteId = $request->get('site_id');

            $metrics = $this->pageRepository->getPipelineMetrics($siteId);

            return $this->jsonResponse([
                'metrics' => $metrics,
                'success' => true
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkUpdateStage(Request $request, string $siteName): JsonResponse
    {
        try {
            $pageIds = $request->get('page_ids', []);
            $status = $request->get('status');

            if (empty($pageIds)) {
                return $this->errorResponse('No page IDs provided', 422);
            }

            if (!$status) {
                return $this->errorResponse('Status is required', 422);
            }

            $validStatuses = ['draft', 'in-review', 'scheduled', 'published'];
            if (!in_array($status, $validStatuses)) {
                return $this->errorResponse('Invalid status', 422);
            }

            $updated = 0;
            foreach ($pageIds as $pageId) {
                if ($this->pageRepository->updatePageStatus($pageId, $status)) {
                    $updated++;
                }
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => "{$updated} pages updated successfully",
                'updated' => $updated
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}