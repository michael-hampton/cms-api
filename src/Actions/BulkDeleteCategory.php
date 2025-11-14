<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\CategoryRepository;

class BulkDeleteCategory
{
    private Database $database;

    public function __construct(Database $database, private readonly CategoryRepository $repository)
    {
        $this->database = $database ?? Database::getInstance();
    }
    public function handle(array $categoryIds): array
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