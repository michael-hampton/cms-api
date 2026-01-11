<?php

namespace App\Services\Cms;

use App\Exceptions\CannotDeleteException;
use App\Exceptions\CategoryAssignmentException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Repositories\Cms\CategoryRepository;

class CategoryService
{
    private Database $database;

    public function __construct(Database $database, private readonly CategoryRepository $repository)
    {
        $this->database = $database ?? Database::getInstance();
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
}