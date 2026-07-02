<?php

namespace App\Services\PublicContent\Preview;

use App\Models\Category;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;

class PublicContentPreviewPageResolver
{
    public function __construct(
        private readonly PublicContentPathResolver $paths,
        private readonly PublicContentPageRepository $pages,
    ) {
    }

    public function resolve(int $siteId, string $path): ?Page
    {
        foreach ($this->paths->resolveCandidates($siteId, $path) as $candidate) {
            $page = $this->pages->findCompletePublishedBySlug($siteId, $candidate->slug);

            if (!$page instanceof Page) {
                continue;
            }

            if ($candidate->pageType !== null && $candidate->pageType !== (string) $page->page_type) {
                continue;
            }

            if (!$this->matchesCategoryConstraints($page, $candidate->categorySlug, $candidate->subcategorySlug)) {
                continue;
            }

            return $page;
        }

        return null;
    }

    private function matchesCategoryConstraints(Page $page, ?string $categorySlug, ?string $subcategorySlug): bool
    {
        if ($categorySlug === null && $subcategorySlug === null) {
            return true;
        }

        $categories = $page->categories ?? null;

        if (!$categories || !method_exists($categories, 'all')) {
            return false;
        }

        $slugs = [];

        foreach ($categories->all() as $category) {
            if ($category instanceof Category) {
                $slugs[] = (string) $category->slug;
            }
        }

        if ($categorySlug !== null && !in_array($categorySlug, $slugs, true)) {
            return false;
        }

        if ($subcategorySlug !== null && !in_array($subcategorySlug, $slugs, true)) {
            return false;
        }

        return true;
    }
}