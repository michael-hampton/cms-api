<?php

namespace App\Controllers;

use App\Framework\Http\jsonResponse;
use App\Framework\Http\Request;
use App\Models\PageGridHistory;
use App\Requests\StorePageGridRequest;
use App\Requests\UpdatePageGridRequest;
use App\Services\PageGridService;

class PageGridController extends Controller
{
    public function __construct(
        protected PageGridService $pageGridService
    ) {
        parent::__construct();
    }

    /**
     * Display a listing of page grids.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $page = $request->input('page', 1); // Add this line
            $search = $request->input('search');
            $layout = $request->input('layout');
            $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            $pageGrids = $this->pageGridService->getPaginatedPageGrids(
                (int)$perPage,
                $search,
                $layout,
                $isActive,
                $sortBy,
                $sortOrder
            );

            return $this->resourceResponse([
                'success' => true,
                'data' => $pageGrids['data']->map(fn($item) => [
                    ...$item->toArray(),
                    'items' => $item->items ?? $item->pages ?? [],
                ]),
                'pagination' => $pageGrids['pagination'] ?? null,
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to fetch page grids',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created page grid.
     */
    public function store(StorePageGridRequest $request): JsonResponse
    {
        try {
            $pageGrid = $this->pageGridService->createPageGrid($request->validated());

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page grid created successfully',
                'data' => $pageGrid->toArray(),
            ], 201);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to create page grid',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified page grid.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $pageGrid = $this->pageGridService->getPageGrid($id);

            if (!$pageGrid) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Page grid not found',
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'data' => $pageGrid->toArray(),
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to fetch page grid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified page grid by slug.
     */
    public function showBySlug(string $slug): JsonResponse
    {
        try {
            $pageGrid = $this->pageGridService->getPageGridBySlug($slug);

            if (!$pageGrid) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Page grid not found',
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'data' => $pageGrid->toArray(),
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to fetch page grid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified page grid.
     */
    public function update(UpdatePageGridRequest $request, int $id): JsonResponse
    {
        try {
            $pageGrid = $this->pageGridService->updatePageGrid($id, $request->validated());

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page grid updated successfully',
                'data' => $pageGrid->toArray(),
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to update page grid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified page grid.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->pageGridService->deletePageGrid($id);

            if (!$result) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Page grid not found',
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page grid deleted successfully',
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to delete page grid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted page grid.
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $result = $this->pageGridService->restorePageGrid($id);

            if (!$result) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Page grid not found',
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page grid restored successfully',
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to restore page grid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permanently delete a page grid.
     */
    public function forceDestroy(int $id): JsonResponse
    {
        try {
            $result = $this->pageGridService->forceDeletePageGrid($id);

            if (!$result) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Page grid not found',
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page grid permanently deleted',
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to permanently delete page grid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicate a page grid.
     */
    public function duplicate(int $id): JsonResponse
    {
        try {
            $pageGrid = $this->pageGridService->duplicatePageGrid($id);

            if (!$pageGrid) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Page grid not found',
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page grid duplicated successfully',
                'data' => $pageGrid->toArray(),
            ], 201);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to duplicate page grid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $pageGrid = $this->pageGridService->toggleActive($id);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page grid status updated successfully',
                'data' => $pageGrid->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to toggle page grid status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a page to the grid.
     */
    public function addPage(Request $request, int $id): JsonResponse
    {

        try {
            $pageGrid = $this->pageGridService->addPageToGrid($id, $request->all());

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page added to grid successfully',
                'data' => $pageGrid,
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to add page to grid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a page from the grid.
     */
    public function removePage(int $id, int $pageIndex): JsonResponse
    {
        try {
            $pageGrid = $this->pageGridService->removePageFromGrid($id, $pageIndex);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page removed from grid successfully',
                'data' => $pageGrid,
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to remove page from grid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a page in the grid.
     */
    public function updatePage(Request $request, int $id, int $pageIndex): JsonResponse
    {
        try {
            $pageGrid = $this->pageGridService->updatePageInGrid($id, $pageIndex, $request->all());

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Page updated successfully',
                'data' => $pageGrid,
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to update page',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder pages in the grid.
     */
    public function reorderPages(Request $request, int $id): JsonResponse
    {
        try {
            $order = $request->input('order');

            // Add validation
            if (!$order || !is_array($order)) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Order array is required',
                ], 400);
            }

            $pageGrid = $this->pageGridService->reorderPagesInGrid($id, $order);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Pages reordered successfully',
                'data' => $pageGrid,
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to reorder pages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function history(Request $request, int $id): JsonResponse
    {
        try {
            $history = $this->pageGridService->getHistory($id);

            return $this->jsonResponse($history->toArray());
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}