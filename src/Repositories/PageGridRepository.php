<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\PageGrid;

class PageGridRepository extends Repository
{
    public function findBySlug(string $slug): ?PageGrid
    {
        return $this->model->with(['creator', 'updater'])->where('slug', $slug)->first();
    }

    public function restore(int $id): bool
    {
        $pageGrid = $this->model->withTrashed()->find($id);

        if (!$pageGrid) {
            return false;
        }

        return $pageGrid->restore();
    }

    public function forceDelete(int $id): bool
    {
        $pageGrid = $this->model->withTrashed()->find($id);

        if (!$pageGrid) {
            return false;
        }

        return $pageGrid->forceDelete();
    }

    public function getActive(): Collection
    {
        return $this->model->active()->with(['creator', 'updater'])->get();
    }

    public function duplicate(int $id): ?Model
    {
        $original = $this->find($id);

        if (!$original) {
            return null;
        }

        $data = $original->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);

        $data['title'] = $data['title'] . ' (Copy)';
        $data['slug'] = $data['slug'] . '-copy';

        return $this->create($data);
    }

    protected function getModelClass(): string
    {
        return PageGrid::class;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = PageGrid::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function paginate(
        int $perPage = 15,
        int $page = 1,
        ?string $search = null,
        ?string $layout = null,
        ?bool $isActive = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): array
    {
        $query = $this->model->query();

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('subtitle', 'LIKE', "%{$search}%");
            });
        }

        // Apply layout filter
        if ($layout) {
            $query->where('layout', $layout);
        }

        // Apply is_active filter
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Use parent paginate or implement pagination
        return $query->paginate($perPage, $page);
    }
}