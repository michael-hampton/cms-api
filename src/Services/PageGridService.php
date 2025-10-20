<?php

namespace App\Services;

use App\Framework\Authorization\Auth;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Model;
use App\Models\PageGrid;
use App\Repositories\PageGridRepository;

class PageGridService
{
    private Database $database;

    public function __construct(
        private AuthenticationService $authenticationService,
        private PageGridRepository $repository,
        ?Database $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function getAllPageGrids(): Collection
    {
        return $this->repository->all();
    }

    public function getPaginatedPageGrids(
        int     $perPage = 15,
        ?string $search = null,
        ?string $layout = null,
        ?bool   $isActive = null,
        string  $sortBy = 'created_at',
        string  $sortOrder = 'desc'
    ): array
    {
        return $this->repository->paginate(
            $perPage,
            1,
            $search,
            $layout,
            $isActive,
            $sortBy,
            $sortOrder
        );
    }

    public function getPageGrid(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function getPageGridBySlug(string $slug): ?PageGrid
    {
        return $this->repository->findBySlug($slug);
    }

    public function createPageGrid(array $data): PageGrid
    {
        return $this->database->transaction(function () use ($data) {
            // Auto-generate slug if not provided
            if (empty($data['slug']) && !empty($data['title'])) {
                $data['slug'] = $this->generateUniqueSlug($data['title']);
            }

            // Set creator
            if ($this->authenticationService->check()) {
                $data['created_by'] = $this->authenticationService->getUserId();
            }

            // Handle migration from 'pages' to 'items'
            if (isset($data['pages']) && !isset($data['items'])) {
                $data['items'] = $data['pages'];
                unset($data['pages']);
            }

            // Initialize items as empty array instead of null
            if (!isset($data['items']) || empty($data['items'])) {
                $data['items'] = [];
            }

            // Validate and normalize items
            if (!empty($data['items'])) {
                $data['items'] = $this->normalizeItems($data['items']);
            }

            if (!empty($data['start_date']) && !empty($data['end_date'])) {
                if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
                    throw new \Exception('Start date must be before end date');
                }
            }

            if (!isset($data['use_hero'])) {
                $data['use_hero'] = true;
            }

            $pageGrid = $this->repository->create($data);

            $this->logHistory($pageGrid->id, 'created', ['data' => $data]);

            return $pageGrid;
        });
    }

    private function normalizeItems(array $items): array
    {
        return array_map(function ($item) {
            // Ensure type is set
            if (!isset($item['type'])) {
                $item['type'] = 'page'; // Default to page for backwards compatibility
            }

            // Validate required fields based on type
            switch ($item['type']) {
                case 'page':
                    if (!isset($item['title']) || !isset($item['slug'])) {
                        throw new \Exception('Pages require title and slug');
                    }
                    break;
                case 'author':
                    if (!isset($item['name'])) {
                        throw new \Exception('Authors require name');
                    }
                    if (!isset($item['slug'])) {
                        $item['slug'] = $this->generateUniqueSlug($item['name']);
                    }
                    break;
                case 'product':
                    if (!isset($item['name'])) {
                        throw new \Exception('Products require name');
                    }
                    if (!isset($item['slug'])) {
                        $item['slug'] = $this->generateUniqueSlug($item['name']);
                    }
                    break;
                default:
                    throw new \Exception("Invalid item type: {$item['type']}");
            }

            return $item;
        }, $items);
    }

    public function updatePageGrid(int $id, array $data): PageGrid
    {
        return $this->database->transaction(function () use ($id, $data) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            // Handle migration from 'pages' to 'items'
            if (isset($data['pages']) && !isset($data['items'])) {
                $data['items'] = $data['pages'];
                unset($data['pages']);
            }

            $changes = $this->detectChanges($pageGrid, $data);

            // Validate and normalize items if provided
            if (isset($data['items'])) {
                $data['items'] = $this->normalizeItems($data['items']);
            }

            // Update slug if title changed and slug not provided
            if (isset($data['title']) && !isset($data['slug']) && $data['title'] !== $pageGrid->title) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $id);
            }

            // Set updater
            if ($this->authenticationService->check()) {
                $data['updated_by'] = $this->authenticationService->getUserId();
            }

            if (!empty($data['start_date']) && !empty($data['end_date'])) {
                if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
                    throw new \Exception('Start date must be before end date');
                }
            }

            $this->repository->update($id, $data);

            if (!empty($changes)) {
                $this->logHistory($id, 'updated', ['changes' => $changes]);
            }

            return $this->repository->find($id);
        });
    }

    private function logHistory(int $pageGridId, string $action, array $changes = []): void
    {
        $userId = $this->authenticationService->check() ? $this->authenticationService->getUserId() : null;

        $this->repository->logHistory($pageGridId, $action, $userId, $changes);
    }

    private function detectChanges($pageGrid, array $newData): array
    {
        $changes = [];
        $fields = ['title', 'subtitle', 'layout', 'columns', 'is_active', 'start_date', 'end_date', 'items', 'use_hero'];

        foreach ($fields as $field) {
            if (isset($newData[$field]) && $pageGrid->$field != $newData[$field]) {
                $changes[$field] = [
                    'old' => $pageGrid->$field,
                    'new' => $newData[$field]
                ];
            }
        }

        return $changes;
    }

    public function getHistory(int $pageGridId): Collection
    {
       return $this->repository->getHistory($pageGridId);
    }

    public function deletePageGrid(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function restorePageGrid(int $id): bool
    {
        return $this->repository->restore($id);
    }

    public function forceDeletePageGrid(int $id): bool
    {
        return $this->repository->forceDelete($id);
    }

    public function duplicatePageGrid(int $id): ?PageGrid
    {
        return $this->database->transaction(function () use ($id) {
            $original = $this->repository->find($id);

            if (!$original) {
                throw new \Exception('Page grid not found');
            }

            $duplicate = $this->repository->duplicate($id);

            if ($duplicate) {
                // Set creator if authenticated
                if ($this->authenticationService->check()) {
                    $duplicate->update(['created_by' => $this->authenticationService->getUserId()]);
                }

                $items = $duplicate->items;

                // Log history for the duplicate
                $this->logHistory($duplicate->id, 'created', [
                    'data' => $duplicate->toArray(),
                    'duplicated_from' => $original->id,
                    'items_count' => count($items ?? []),
                    'item_types' => $this->getItemTypeCounts($items ?? [])
                ]);
            }

            return $duplicate;
        });
    }

    private function getItemTypeCounts(array $items): array
    {
        $counts = ['page' => 0, 'author' => 0, 'product' => 0];

        foreach ($items as $item) {
            $type = $item['type'] ?? 'page';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        return $counts;
    }

    public function addPageToGrid(int $id, array $pageData): PageGrid
    {
        return $this->database->transaction(function () use ($id, $pageData) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            $pageGrid->addPage($pageData);
            $pageGrid->save();

            return $pageGrid;
        });
    }

    public function removePageFromGrid(int $id, int $pageIndex): PageGrid
    {
        return $this->database->transaction(function () use ($id, $pageIndex) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            $pageGrid->removePage($pageIndex);

            // REMOVED: Manual JSON encoding - let casting handle it
            // $pageGrid->pages = is_array($pageGrid->pages) ? json_encode($pageGrid->pages ?? []) : $pageGrid->pages;

            $pageGrid->save();

            return $pageGrid;
        });
    }

    public function updatePageInGrid(int $id, int $pageIndex, array $pageData): PageGrid
    {
        return $this->database->transaction(function () use ($id, $pageIndex, $pageData) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            $pageGrid->updatePage($pageIndex, $pageData);
            $pageGrid->save();

            return $pageGrid->fresh();
        });
    }

    public function reorderPagesInGrid(int $id, array $order): PageGrid
    {
        return $this->database->transaction(function () use ($id, $order) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            $pageGrid->reorderPages($order);
            $pageGrid->save();

            return $pageGrid->fresh();
        });
    }

    public function toggleActive(int $id): PageGrid
    {
        return $this->database->transaction(function () use ($id) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            $pageGrid->is_active = !$pageGrid->is_active;
            $pageGrid->save();

            return $pageGrid;
        });
    }

    protected function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->repository->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function addItemToGrid(int $id, string $type, array $itemData): PageGrid
    {
        return $this->database->transaction(function () use ($id, $type, $itemData) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            $item = array_merge($itemData, ['type' => $type]);
            $pageGrid->addItem($item);
            $pageGrid->save();

            return $pageGrid;
        });
    }

    public function removeItemFromGrid(int $id, int $itemIndex): PageGrid
    {
        return $this->database->transaction(function () use ($id, $itemIndex) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            $pageGrid->removeItem($itemIndex);
            $pageGrid->save();

            return $pageGrid;
        });
    }

    public function updateItemInGrid(int $id, int $itemIndex, array $itemData): PageGrid
    {
        return $this->database->transaction(function () use ($id, $itemIndex, $itemData) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            $pageGrid->updateItem($itemIndex, $itemData);
            $pageGrid->save();

            return $pageGrid->fresh();
        });
    }
}