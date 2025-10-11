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

            // FIXED: Initialize pages as empty array instead of null
            if (!isset($data['pages']) || empty($data['pages'])) {
                $data['pages'] = [];
            }

            return $this->repository->create($data);
        });
    }

    public function updatePageGrid(int $id, array $data): PageGrid
    {
        return $this->database->transaction(function () use ($id, $data) {
            $pageGrid = $this->repository->find($id);

            if (!$pageGrid) {
                throw new \Exception('Page grid not found');
            }

            // Update slug if title changed and slug not provided
            if (isset($data['title']) && !isset($data['slug']) && $data['title'] !== $pageGrid->title) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $id);
            }

            // Set updater
            if ($this->authenticationService->check()) {
                $data['updated_by'] = $this->authenticationService->getUserId();
            }

            $this->repository->update($id, $data);

            // FIXED: Return fresh model instance to get updated data
            return $this->repository->find($id);
        });
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
            $duplicate = $this->repository->duplicate($id);

            if ($duplicate && $this->authenticationService->check()) {
                $duplicate->update(['created_by' => $this->authenticationService->getUserId()]);
            }

            return $duplicate;
        });
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
}