<?php

namespace App\Controllers\Cms;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\Site;
use App\Resources\PageHistoryResource;
use App\Services\Cms\Pages\PageHistoryService;
use Exception;

class PageHistoryController extends Controller
{
    public function __construct(
        private PageHistoryService $historyService
    ) {
        parent::__construct();
    }

    public function index(int $pageId, string $siteName): JsonResponse
    {
        try {
            $limit = $_GET['limit'] ?? 50;
            $history = $this->historyService->getPageHistory($pageId, (int)$limit);

            $data = $history->map(function($entry) {
                return PageHistoryResource::make($entry)->toArray();
            })->all();

            return $this->jsonResponse(['history' => $data]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id, string $siteName): JsonResponse
    {
        try {
            $entry = $this->historyService->getHistoryEntry($id);

            if (!$entry) {
                return $this->errorResponse('History entry not found', 404);
            }

            return $this->jsonResponse([
                'history' => PageHistoryResource::make($entry)->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function recent(Request $request, string $siteName): JsonResponse
    {
        try {
            $limit = $request->get('limit', 20);
            $siteId = Site::resolveSite($siteName);

            $history = $this->historyService->getRecentHistory($siteId, (int)$limit);

            $data = $history->map(function($entry) {
                return PageHistoryResource::make($entry)->toArray();
            })->all();

            return $this->jsonResponse(['history' => $data]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function userHistory(int $userId, string $siteName): JsonResponse
    {
        try {
            $limit = $_GET['limit'] ?? 50;
            $history = $this->historyService->getUserHistory($userId, (int)$limit);

            $data = $history->map(function($entry) {
                return PageHistoryResource::make($entry)->toArray();
            })->all();

            return $this->jsonResponse(['history' => $data]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function restore(int $historyId, string $siteName): JsonResponse
    {
        try {
            $page = $this->historyService->restoreFromHistory($historyId);

            return $this->jsonResponse([
                'message' => 'Page restored successfully',
                'page' => $page->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}