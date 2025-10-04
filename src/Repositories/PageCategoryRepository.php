<?php

namespace App\Repositories;

use App\Models\PageCategory;

class PageCategoryRepository extends Repository
{
    private $categoryRepository;

    public function __construct()
    {
        parent::__construct();
        $this->categoryRepository = new CategoryRepository();
    }

    protected function getModelClass(): string
    {
        return PageCategory::class;
    }

    public function syncCategories(int $pageId, array $categoryNames): void
    {
        // Delete existing page categories
        $this->database->delete('page_categories', ['page_id' => $pageId]);

        // Process new categories
        foreach ($categoryNames as $categoryName) {
            if (!empty(trim($categoryName))) {
                // Find or create category
                $category = $this->categoryRepository->findOrCreateByName(trim($categoryName));

                // Create page-category relationship
                $this->create([
                    'page_id' => $pageId,
                    'category_id' => $category->id
                ]);
            }
        }
    }

    public function syncCategoriesByIds(int $pageId, array $categoryIds): void
    {
        // Delete existing page categories
        $this->database->delete('page_categories', ['page_id' => $pageId]);

        // Create new relationships
        foreach ($categoryIds as $categoryId) {
            if (is_numeric($categoryId)) {
                $this->create([
                    'page_id' => $pageId,
                    'category_id' => (int) $categoryId
                ]);
            }
        }
    }

    public function getPageCategories(int $pageId): array
    {
        $results = $this->database->select(
            "SELECT c.* FROM categories c 
             INNER JOIN page_categories pc ON c.id = pc.category_id 
             WHERE pc.page_id = ? 
             ORDER BY c.sort_order ASC, c.name ASC",
            [$pageId]
        );

        $models = [];
        foreach ($results as $data) {
            $model = new Category($data);
            $model->exists = true;
            $model->original = $model->attributes;
            $models[] = $model;
        }

        return $models;
    }

    public function getPageCategoryIds(int $pageId): array
    {
        $results = $this->database->select(
            "SELECT category_id FROM page_categories WHERE page_id = ?",
            [$pageId]
        );

        return array_column($results, 'category_id');
    }

    public function getPagesByCategory(int $categoryId, string $status = 'published'): array
    {
        $results = $this->database->select(
            "SELECT p.* FROM pages p 
             INNER JOIN page_categories pc ON p.id = pc.page_id 
             WHERE pc.category_id = ? AND p.status = ? 
             ORDER BY p.created_at DESC",
            [$categoryId, $status]
        );

        $models = [];
        foreach ($results as $data) {
            $model = new \App\Models\Page($data);
            $model->exists = true;
            $model->original = $model->attributes;
            $models[] = $model;
        }

        return $models;
    }

    public function getCategoryStats(int $categoryId): array
    {
        $results = $this->database->select(
            "SELECT 
                COUNT(*) as total_pages,
                COUNT(CASE WHEN p.status = 'published' THEN 1 END) as published_pages,
                COUNT(CASE WHEN p.status = 'draft' THEN 1 END) as draft_pages
             FROM page_categories pc
             LEFT JOIN pages p ON pc.page_id = p.id
             WHERE pc.category_id = ?",
            [$categoryId]
        );

        return $results[0] ?? [
            'total_pages' => 0,
            'published_pages' => 0,
            'draft_pages' => 0
        ];
    }
}