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
        $this->withoutSiteFilter();
    }

    protected function getModelClass(): string
    {
        return PageCategory::class;
    }

    public function syncCategories(int $pageId, array $categoryNames, $siteId): void
    {
        // Delete existing page categories
        $this->database->delete('page_categories', ['page_id' => $pageId]);;

        // Process new categories
        foreach ($categoryNames as $categoryName) {
            if (!empty(trim($categoryName))) {
                // Find or create category
                $category = $this->categoryRepository->findOrCreateByName(trim($categoryName), $siteId);

                // Create page-category relationship
                $this->create([
                    'page_id' => $pageId,
                    'category_id' => $category->id
                ]);
            }
        }
    }
}