<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Models\PageHistory;
use App\Repositories\Cms\Pages\PageHistoryRepository;
use App\Repositories\Cms\Pages\PageRepository;

/**
 * Exposes page version history to contributors for the editor sidebar.
 *
 * Contributors may only access history for pages they own.
 * Restore calls PageHistoryService which already handles snapshot application
 * and records the restoration action in page_history.
 *
 * Routes:
 *   GET  /api/{site}/open-collab/pages/{pageId}/history
 *   POST /api/{site}/open-collab/pages/{pageId}/history/{historyId}/restore
 */
class ArticleHistoryController extends Controller
{
    public function __construct(
        private readonly PageRepository        $pageRepository,
        private readonly PageHistoryRepository $historyRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/pages/{pageId}/history
     * Returns version history for the given page, newest first.
     * Contributor must own the page.
     */
    public function index(int $pageId): JsonResponse
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page || (int)$page->contributor_id !== Auth::id()) {
            return $this->errorResponse('Page not found.', 404);
        }

        $limit = min((int)($_GET['limit'] ?? 50), 100);
        $history = $this->historyRepository->getPageHistory($pageId, $limit);

        $history = $history
            ->map(fn($c) => $this->formatEntry($c))
            ->toArray();

        return $this->jsonResponse(['history' => $history]);
    }

    private function formatEntry(PageHistory $entry): array
    {
        return [
            'id' => $entry->id,
            'action' => $entry->action,
            'action_label' => $entry->getActionLabel(),
            'change_summary' => $entry->getChangeSummary(),
            'user_name' => $entry->getUserName(),
            'created_at' => $entry->created_at,
            'snapshot' => $entry->snapshot, // included so JS can preview content
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/pages/{pageId}/history/{historyId}/restore
     *
     * Loads the snapshot from the given history entry, overwrites the page's
     * current content, and saves — recording a new 'restored' history entry.
     * Returns the updated page so the editor can sync.
     */
    public function restore(int $pageId, int $historyId): JsonResponse
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page || (int)$page->contributor_id !== Auth::id()) {
            return $this->errorResponse('Page not found.', 404);
        }

        $entry = $this->historyRepository->findById($historyId);

        if (!$entry || (int)$entry->page_id !== $pageId) {
            return $this->errorResponse('History entry not found.', 404);
        }

        $snapshot = $entry->snapshot;

        if (empty($snapshot)) {
            return $this->errorResponse('No snapshot available for this version.', 422);
        }

        // Apply snapshot fields to the page
        $updateData = array_filter([
            'title' => $snapshot['title'] ?? null,
            'content' => $snapshot['content'] ?? null,
            'slug' => $snapshot['slug'] ?? null,
        ], fn($v) => $v !== null);

        if (empty($updateData)) {
            return $this->errorResponse('Snapshot contains no restorable content.', 422);
        }

        $this->pageRepository->update($pageId, $updateData);

        // Record the restoration in history
        $this->historyRepository->create([
            'page_id' => $pageId,
            'user_id' => Auth::id(),
            'site_id' => $page->site_id,
            'action' => 'restored',
            'description' => "Restored from version #{$historyId}",
            'changes' => ['restored_from' => $historyId],
            'snapshot' => $updateData,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $updated = $this->pageRepository->find($pageId);

        return $this->jsonResponse([
            'message' => 'Version restored successfully.',
            'page' => [
                'id' => $updated->id,
                'title' => $updated->title,
                'content' => $updated->content,
                'slug' => $updated->slug,
                'status' => $updated->status,
            ],
        ]);
    }
}