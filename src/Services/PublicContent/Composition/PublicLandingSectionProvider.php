<?php

namespace App\Services\PublicContent\Composition;

use App\Framework\Support\Collection;
use App\Models\Page;
use App\Repositories\PublicContent\PublicCategoryRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Images\PublicContentListingImageHydrator;

final class PublicLandingSectionProvider
{
    public function __construct(
        private readonly PublicCategoryRepository $categories,
        private readonly PublicContentPageRepository $pages,
        private readonly PublicContentListingImageHydrator $listingImages,
        private readonly PublicContentConfigSource $publicContentConfig,
    ) {
    }

    public function for(Page $page, int $siteId): array
    {
        $pageTypes = $this->publicContentConfig->get($siteId, 'widgets.category-pages.page_types', ['landing-page']);

        if (is_array($pageTypes)
            && !in_array('*', $pageTypes, true)
            && !in_array((string) $page->page_type, $pageTypes, true)
        ) {
            return [];
        }

        $pagesPerSection = max(1, (int) $this->publicContentConfig->get($siteId, 'widgets.category-pages.pages_per_section', 6));
        // Never require more pages than we fetch, or every section is skipped.
        $minPages = min(
            $pagesPerSection,
            max(1, (int) $this->publicContentConfig->get($siteId, 'widgets.category-pages.min_pages', 3)),
        );

        $categories = $this->categories->getAll($siteId);
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[] = (int) $category->id;
        }

        $pagesByCategory = $this->pages->getPublishedPagesForCategories($siteId, $categoryIds, $pagesPerSection);

        $sections = [];
        foreach ($categories as $category) {
            $items = $pagesByCategory[(int) $category->id] ?? new Collection();

            if ($items->count() < $minPages) {
                continue;
            }

            $this->listingImages->hydrate($items);

            $sections[] = [
                'category' => $category,
                'pages' => $items,
            ];
        }

        return $sections;
    }
}
