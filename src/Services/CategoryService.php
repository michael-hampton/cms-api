<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
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

        return [
            'can_delete' => $pagesCount === 0,
            'pages_count' => $pagesCount,
            'requires_reassignment' => $pagesCount > 0
        ];
    }

    public function getAlternativeCategories(int $categoryId): Collection
    {
        return $this->repository->getAlternatives($categoryId);
    }
}