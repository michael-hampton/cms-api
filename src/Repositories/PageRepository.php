<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Block;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageAccessRole;
use App\Models\PageCategory;
use App\Models\PageCustomField;
use App\Models\PageMetadata;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Models\PageTag;
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
        $query = Page::with(['categories', 'tags', 'metadata', 'author', 'blocks', 'seo', 'settings', 'social', 'customFields']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function findBySlug(string $slug): ?Model
    {
        $query = Page::where('slug', $slug);
        return $this->applySiteFilter($query)->first();
    }

    public function getPublishedPages(): array
    {
        $query = Page::published()->orderBy('created_at', 'desc');
        return $this->applySiteFilter($query)->get();
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
        })->filter(function($page) {
            // Filter by current site
            return $page && $page->site_id === $this->siteId;
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
            return $item->page
                && $item->page->status == 'published'
                && $item->page->site_id === $this->siteId;
        })->map(function($item) {
            return $item->page;
        });
    }

    public function getCompletePageData(int $pageId): ?Page
    {
        return Page::with([
            'blocks', 'categories', 'tags', 'metadata',
            'seo', 'settings', 'social', 'customFields', 'customFields.customFieldDefinition'
        ])->find($pageId);
    }

    /**
     * Duplicate blocks from source page to target page
     */
    public function duplicateBlocks(int $sourcePageId, int $targetPageId): void
    {
        $blocks = Block::where('page_id', $sourcePageId)
            ->orderBy('order')
            ->get();

        foreach ($blocks as $block) {
            Block::create([
                'page_id' => $targetPageId,
                'type' => $block->type,  // Changed from $block['type']
                'data' => $block->data,  // Changed from $block['data']
                'order' => $block->order  // Changed from $block['order']
            ]);
        }
    }

    /**
     * Duplicate metadata from source page to target page
     */
    public function duplicateMetadata(int $sourcePageId, int $targetPageId): void
    {
        $metadata = PageMetadata::where('page_id', $sourcePageId)
            ->first();

        if ($metadata) {
            $data = $metadata->toArray();
            unset($data['id'], $data['page_id']);
            $data['page_id'] = $targetPageId;
            PageMetadata::create($data);
        }
    }

    /**
     * Duplicate SEO data from source page to target page
     */
    public function duplicateSeo(int $sourcePageId, int $targetPageId): void
    {
        $seo = PageSeo::where('page_id', $sourcePageId)
            ->first();

        if ($seo) {
            $data = $seo->toArray();
            unset($data['id'], $data['page_id']);
            $data['page_id'] = $targetPageId;
            PageSeo::create($data);
        }
    }

    /**
     * Duplicate settings from source page to target page
     */
    public function duplicateSettings(int $sourcePageId, int $targetPageId): void
    {
        $settings = PageSettings::where('page_id', $sourcePageId)
            ->first();

        if ($settings) {
            $data = $settings->toArray();
            unset($data['id'], $data['page_id']);
            $data['page_id'] = $targetPageId;
            PageSettings::create($data);
        }
    }

    /**
     * Duplicate social data from source page to target page
     */
    public function duplicateSocial(int $sourcePageId, int $targetPageId): void
    {
        $social = PageSocial::where('page_id', $sourcePageId)
            ->first();

        if ($social) {
            $data = $social->toArray();
            unset($data['id'], $data['page_id']);
            $data['page_id'] = $targetPageId;
            PageSocial::create($data);
        }
    }

    /**
     * Duplicate categories from source page to target page
     */
    public function duplicateCategories(int $sourcePageId, int $targetPageId): void
    {
        $categories = PageCategory::where('page_id', $sourcePageId)
            ->get();

        foreach ($categories as $category) {
            PageCategory::create([
                'page_id' => $targetPageId,
                'category_id' => $category->category_id  // Changed from $category['category_id']
            ]);
        }
    }

    /**
     * Duplicate tags from source page to target page
     */
    public function duplicateTags(int $sourcePageId, int $targetPageId): void
    {
        $tags = PageTag::where('page_id', $sourcePageId)
            ->get();

        foreach ($tags as $tag) {
            PageTag::create([
                'page_id' => $targetPageId,
                'tag_id' => $tag->tag_id  // Changed from $tag['tag_id']
            ]);
        }
    }

    /**
     * Duplicate custom fields from source page to target page
     */
    public function duplicateCustomFields(int $sourcePageId, int $targetPageId): void
    {
        $customFields = PageCustomField::where('page_id', $sourcePageId)
            ->get();

        foreach ($customFields as $field) {
            $data = $field->toArray();
            unset($data['id'], $data['page_id']);
            $data['page_id'] = $targetPageId;
            PageCustomField::create($data);
        }
    }

    /**
     * Duplicate access roles from source page to target page
     */
    public function duplicateAccessRoles(int $sourcePageId, int $targetPageId): void
    {
        $roles = PageAccessRole::where('page_id', $sourcePageId)
            ->get();

        foreach ($roles as $role) {
            PageAccessRole::create([
                'page_id' => $targetPageId,
                'role_id' => $role->role_id  // Changed from $role['role_id']
            ]);
        }
    }

}