<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Exceptions\CategoryAssignmentException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Category;
use App\Repositories\CategoryRepository;

class CategoryService
{
    private Database $database;
    protected CategoryRepository $repository;

    public function __construct(Database $database, CategoryRepository $repository)
    {
        $this->database = $database ?? Database::getInstance();
        $this->repository = $repository;
    }

    public function delete(int $categoryId, ?int $reassignToCategoryId = null): bool
    {
        $category = $this->repository->find($categoryId);

        if (!$category) {
            throw new \Exception('Category not found');
        }

        // Check for child categories
        $childrenCount = $category->children()->count();
        if ($childrenCount > 0) {
            throw new CategoryAssignmentException("Cannot delete category with {$childrenCount} subcategories. Please delete or reassign subcategories first.");
        }

        $pagesCount = $this->repository->getPagesByCategoryId($categoryId)->count();

        if ($pagesCount > 0) {
            if ($reassignToCategoryId === null) {
                throw new CannotDeleteException('category', $pagesCount);
            }

            if ($reassignToCategoryId === $categoryId) {
                throw new \InvalidArgumentException('Cannot reassign to the same category being deleted');
            }

            $reassignCategory = $this->repository->find($reassignToCategoryId);

            if (!$reassignCategory) {
                throw new \Exception('Reassignment category not found');
            }

            $this->database->transaction(function () use ($categoryId, $category, $reassignToCategoryId) {
                // Get pages and update them individually
                $pages = $this->repository->getPagesByCategoryId($categoryId);
                foreach ($pages as $page) {
                    $page->category_id = $reassignToCategoryId;
                    $page->save();
                }
                $category->delete();
            });

            return true;
        }

         return $this->repository->delete($categoryId);
    }

    public function checkDeletable(int $categoryId): array
    {
        $category = $this->repository->find($categoryId);

        if (!$category) {
            throw new \Exception('Category not found');
        }

        $pagesCount = $category->pages()->count();
        $childrenCount = $category->children()->count();

        return [
            'can_delete' => $pagesCount === 0 && $childrenCount === 0,
            'pages_count' => $pagesCount,
            'children_count' => $childrenCount,
            'requires_reassignment' => $pagesCount > 0,
            'has_children' => $childrenCount > 0
        ];
    }

    public function getAlternativeCategories(int $categoryId): Collection
    {
        return $this->repository->getAlternatives($categoryId);
    }

    public function duplicateCategory(int $categoryId, ?string $newName = null): bool
    {
        return $this->database->transaction(function() use ($categoryId, $newName) {
            $originalCategory = $this->repository->find($categoryId);

            if (!$originalCategory) {
                throw new \Exception("Category not found");
            }

            $data = [
                'name' => $newName ?? ($originalCategory->name . ' (Copy)'),
                'description' => $originalCategory->description,
                'parent_id' => $originalCategory->parent_id,
                'status' => 'inactive',
                'site_id' => $originalCategory->site_id,
                'seo_title' => $originalCategory->seo_title,
                'seo_description' => $originalCategory->seo_description,
                'no_index' => $originalCategory->no_index ?? false,
                'canonical_url' => null, // Don't copy canonical URL
            ];

            // Generate unique slug
            $baseName = $data['name'];
            $slug = Str::slug($baseName);
            $counter = 1;

            while ($this->repository->findBySlug($slug)) {
                $slug = Str::slug($baseName . '-' . $counter);
                $counter++;
            }

            $data['slug'] = $slug;

            $newCategory = $this->repository->create($data);

            if (!$newCategory) {
                throw new \Exception("Failed to create duplicate category");
            }

            // Duplicate child categories recursively
            $children = $originalCategory->children();
            foreach ($children as $child) {
                $this->duplicateCategoryRecursive($child, $newCategory->id);
            }

            return $newCategory !== null;
        });
    }

    private function duplicateCategoryRecursive(Category $category, int $newParentId): void
    {
        $data = [
            'name' => $category->name,
            'description' => $category->description,
            'parent_id' => $newParentId,
            'status' => 'inactive',
            'site_id' => $category->site_id,
            'seo_title' => $category->seo_title,
            'seo_description' => $category->seo_description,
            'no_index' => $category->no_index ?? false,
            'canonical_url' => null,
            'color' => $category->color,
            'icon' => $category->icon,
            'sort_order' => $category->sort_order,
            'is_active' => false,
        ];

        // Generate unique slug
        $baseName = $data['name'];
        $slug = Str::slug($baseName);
        $counter = 1;

        while ($this->repository->findBySlug($slug)) {
            $slug = Str::slug($baseName . '-' . $counter);
            $counter++;
        }

        $data['slug'] = $slug;

        $newCategory = $this->repository->create($data);

        // Recursively duplicate children
        $children = $category->children();
        foreach ($children as $child) {
            $this->duplicateCategoryRecursive($child, $newCategory->id);
        }
    }

    public function bulkDelete(array $categoryIds): array
    {
        return $this->database->transaction(function() use ($categoryIds) {
            $deleted = [];
            $failed = [];

            foreach ($categoryIds as $categoryId) {
                try {
                    $category = $this->repository->find($categoryId);

                    if (!$category) {
                        $failed[] = ['id' => $categoryId, 'reason' => 'Category not found'];
                        continue;
                    }

                    $childrenCount = $category->children()->count();
                    if ($childrenCount > 0) {
                        $failed[] = [
                            'id' => $categoryId,
                            'reason' => "Category has {$childrenCount} subcategories"
                        ];
                        continue;
                    }

                    $pagesCount = $this->repository->getPagesByCategoryId($categoryId)->count();

                    if ($pagesCount > 0) {
                        $failed[] = [
                            'id' => $categoryId,
                            'reason' => "Category has {$pagesCount} associated pages"
                        ];
                        continue;
                    }

                    if ($this->repository->delete($categoryId)) {
                        $deleted[] = $categoryId;
                    } else {
                        $failed[] = ['id' => $categoryId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $categoryId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($categoryIds)
            ];
        });
    }
}