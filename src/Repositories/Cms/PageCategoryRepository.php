<?php

namespace App\Repositories\Cms;

use App\Models\PageCategory;
use App\Repositories\Repository;

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

    public function syncCategories(int $pageId, array $categoriesData, int $siteId): void
    {
        // Delete existing page categories for this page and site
        PageCategory::where('page_id', $pageId)
            ->delete();

        // Process categories (can be IDs or names)
        foreach ($categoriesData as $categoryData) {
            if (empty($categoryData)) {
                continue;
            }

            // Skip whitespace-only strings
            if (is_string($categoryData) && empty(trim($categoryData))) {
                continue;
            }

            $category = null;

            // Check if it's a numeric ID
            if (is_numeric($categoryData)) {
                $category = $this->categoryRepository->find($categoryData);

                // Verify category belongs to the correct site
                if ($category && $category->site_id != $siteId) {
                    continue; // Skip categories from other sites
                }
            } else {
                // It's a name, find or create
                $category = $this->categoryRepository->findOrCreateByName(trim($categoryData), $siteId);
            }

            if ($category) {
                $this->create([
                    'page_id' => $pageId,
                    'category_id' => $category->id,
                    'site_id' => $siteId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
}