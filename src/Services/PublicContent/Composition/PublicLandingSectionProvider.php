<?php

namespace App\Services\PublicContent\Composition;

use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\PublicContent\PublicCategoryRepository;

final class PublicLandingSectionProvider
{
    public function __construct(
        private readonly PublicCategoryRepository $categories,
        private readonly PageRepository $pages,
    ) {
    }

    public function for(Page $page, int $siteId): array
    {
        if ((string)$page->page_type !== 'landing-page') {
            return [];
        }

        $sections = [];

        foreach ($this->categories->getAll($siteId) as $category) {
            $items = $this->pages->getPagesByCategory((int)$category->id, 6, $siteId);

            if ($items->count() < 3) {
                continue;
            }

            $sections[] = [
                'category' => $category,
                'pages' => $items,
            ];
        }

        return $sections;
    }
}
