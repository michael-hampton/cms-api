<?php

namespace App\Services\PublicContent\Composition;

use App\Enums\PublicContent\PublicPageType;
use App\Framework\Support\Collection;
use App\Models\Page;
use App\Repositories\PublicContent\PublicCategoryRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Images\PublicContentListingImageHydrator;

final class PublicLandingSectionProvider
{
    public function __construct(
        private readonly PublicCategoryRepository $categories,
        private readonly PublicContentPageRepository $pages,
        private readonly PublicContentListingImageHydrator $listingImages,
    ) {
    }

    public function for(Page $page, int $siteId): array
    {
        if (PublicPageType::fromPage($page->page_type) !== PublicPageType::LandingPage) {
            return [];
        }

        $categories = $this->categories->getAll($siteId);
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[] = (int) $category->id;
        }

        $pagesByCategory = $this->pages->getPublishedPagesForCategories($siteId, $categoryIds, 6);

        $sections = [];
        foreach ($categories as $category) {
            $items = $pagesByCategory[(int) $category->id] ?? new Collection();

            if ($items->count() < 3) {
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
