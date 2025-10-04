<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\PageMetadata;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;


class PageRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::createPageConfiguration();
        $this->searchEngine = new SearchEngine($config);
    }

    protected function getModelClass(): string
    {
        return Page::class;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Page::with(['categories', 'tags', 'metadata', 'author', 'blocks', 'seo', 'settings', 'social']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function findBySlug(string $slug): ?Model
    {
        return Page::where('slug', $slug)->first();
    }

    public function getPublishedPages(): array
    {
        return Page::published()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Quick search using the SearchEngine infrastructure
     * This is a convenience wrapper around the search() method
     */
    public function quickSearch(string $query = '', array $options = []): Collection
    {
        $criteria = new SearchCriteria();

        // Set search query
        if (!empty($query)) {
            $criteria->setSearchQuery($query);
        }

        // Set status filter
        $status = $options['status'] ?? 'published';
        if (!empty($status)) {
            $criteria->addFilter('status', $status);
        }

        // Set limit (per_page in SearchCriteria)
        $limit = $options['limit'] ?? 20;
        $criteria->setPerPage($limit);

        // Disable pagination to get Collection instead of PaginatedResult
        $criteria->setPage(1);

        // Build query with optional relationships
        $with = $options['with'] ?? [];
        $queryBuilder = empty($with) ? Page::query() : Page::with($with);

        // Use SearchEngine
        $result = $this->searchEngine->search($queryBuilder, $criteria);

        // Return as Collection (extract data from paginated result)
        return collect($result->getData());
    }

    public function getPagesByCategory(int $categoryId, ?int $limit = null): Collection
    {
        $query = PageCategory::with(['category', 'page'])
            ->where('category_id', $categoryId)
            ->orderBy('created_at', 'desc');



        if ($limit) {
            $query->limit($limit);
        }

        $categories = $query->get();

        return $categories->map(function($item) {
            return $item->page;
        });
    }

    public function getRecentPages(int $limit = 10): array
    {
        return Page::published()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getFeaturedPages(?int $limit = null): Collection
    {
        $query = PageMetadata::with(['page'])->where('featured', 1)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        $results = $query->get();

        return $results->filter(function($item) {
            return $item->page->status == 'published';
        });
    }

    public function getCompletePageData(int $pageId): ?Page
    {
        return Page::with([
            'blocks', 'categories', 'tags', 'metadata',
            'seo', 'settings', 'social', 'customFields', 'customFields.customFieldDefinition'
        ])->find($pageId);
    }
}