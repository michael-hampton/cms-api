<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Author;
use App\Models\Block;
use App\Models\Category;
use App\Models\CustomFieldDefinition;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageAccessRole;
use App\Models\PageAuthor;
use App\Models\PageCategory;
use App\Models\PageCustomField;
use App\Models\PageMetadata;
use App\Models\PageRegionSet;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Models\PageTag;
use App\Models\PageTerritory;
use App\Models\RegionSet;
use App\Models\Tag;
use App\Models\Territory;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;
use App\Services\RelationCaster;


class PageRepository extends Repository
{
    private SearchEngine $searchEngine;
    private RelationCaster $relationCaster;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('page');
        $this->searchEngine = new SearchEngine($config);
        $this->relationCaster = new RelationCaster();
    }

    protected function getModelClass(): string
    {
        return Page::class;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Page::with([
            'categories',
            'tags',
            'metadata',
            'author',
            'blocks',
            'seo',
            'settings',
            'social',
            'customFields',
            'customFields.customFieldDefinition',
            'authors',
            'pageAuthors',
            'pageAuthors.author',
            'regionSets',
            'territories'
        ]);;
        return $this->searchEngine->search($query, $criteria);
    }

    public function findBySlug(string $slug): ?Model
    {
        $query = Page::where('slug', $slug);
        return $this->applySiteFilter($query)->first();
    }

    public function getPublishedPages(): Collection
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

        if (!empty($options['site_id'])) {
            $criteria->addFilter('site_id', $options['site_id']);
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

    public function getPagesByCategory(int $categoryId, ?int $limit = null, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;
        $query = PageCategory::with(['category', 'page'])
            ->where('category_id', $categoryId)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        $categories = $query->get();

        return $categories->map(function ($item) {
            return $item->page;
        })->filter(function ($page) use ($siteId) {
            // Filter by current site
            return $page && $page->site_id === $siteId;
        });
    }

    public function getRecentPages(int $limit = 10): array
    {
        return Page::published()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getFeaturedPages(?int $limit = null, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;
        $query = PageMetadata::with(['page'])->where('featured', 1)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        $results = $query->get();

        return $results->filter(function ($item) use ($siteId) {
            return $item->page
                && $item->page->status == 'published'
                && $item->page->site_id === $siteId;
        })->map(function ($item) {
            return $item->page;
        });
    }

    public function getCompletePageData(int $pageId): ?Page
    {
        return Page::with([
            'blocks', 'categories', 'tags', 'metadata',
            'seo', 'settings', 'social', 'customFields',
            'customFields.customFieldDefinition',
            'authors', 'pageAuthors', 'pageAuthors.author', 'regionSets', 'territories'
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
            $castedData = $this->relationCaster->castForDuplication('metadata', $data);
            $castedData['page_id'] = $targetPageId;

            PageMetadata::create($castedData);
        }
    }

    /**
     * Duplicate SEO data from source page to target page
     */
    public function duplicateSeo(int $sourcePageId, int $targetPageId): void
    {
        $seo = PageSeo::where('page_id', $sourcePageId)->first();

        if ($seo) {
            $data = $seo->toArray();
            $castedData = $this->relationCaster->castForDuplication('seo', $data);
            $castedData['page_id'] = $targetPageId;

            PageSeo::create($castedData);
        }
    }

    /**
     * Duplicate settings from source page to target page
     */
    public function duplicateSettings(int $sourcePageId, int $targetPageId): void
    {
        $settings = PageSettings::where('page_id', $sourcePageId)->first();

        if ($settings) {
            $data = $settings->toArray();
            $castedData = $this->relationCaster->castForDuplication('settings', $data);
            $castedData['page_id'] = $targetPageId;

            PageSettings::create($castedData);
        }
    }

    /**
     * Duplicate social data from source page to target page
     */
    public function duplicateSocial(int $sourcePageId, int $targetPageId): void
    {
        $social = PageSocial::where('page_id', $sourcePageId)->first();

        if ($social) {
            $data = $social->toArray();
            $castedData = $this->relationCaster->castForDuplication('social', $data);
            $castedData['page_id'] = $targetPageId;
            PageSocial::create($castedData);
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

    public function duplicatePageAuthors(int $sourcePageId, int $targetPageId): void
    {
        $authors = PageAuthor::where('page_id', $sourcePageId)
            ->orderBy('sort_order')
            ->get();

        foreach ($authors as $author) {
            PageAuthor::create([
                'page_id' => $targetPageId,
                'author_id' => $author->author_id,
                'role' => $author->role,
                'sort_order' => $author->sort_order
            ]);
        }
    }

    public function duplicateRegionSets(int $sourcePageId, int $targetPageId): void
    {
        $regionSets = PageRegionSet::where('page_id', $sourcePageId)->get();

        foreach ($regionSets as $regionSet) {
            PageRegionSet::create([
                'page_id' => $targetPageId,
                'region_set_id' => $regionSet->region_set_id,
                'site_id' => $regionSet->site_id
            ]);
        }
    }

    public function duplicateTerritories(int $sourcePageId, int $targetPageId): void
    {
        $territories = PageTerritory::where('page_id', $sourcePageId)->get();

        foreach ($territories as $territory) {
            PageTerritory::create([
                'page_id' => $targetPageId,
                'territory_id' => $territory->territory_id,
                'site_id' => $territory->site_id
            ]);
        }
    }

    /**
     * Check if slug exists in a specific site
     */
    public function slugExistsInSite(string $slug, int $siteId): bool
    {
        return Page::where('slug', $slug)
            ->where('site_id', $siteId)
            ->exists();
    }

    /******************************** Clone to site functions ***************************/

    /**
     * Duplicate categories from source page to target page
     */
    public function duplicateCategoriesToSite(int $sourcePageId, int $targetPageId, int $targetSiteId): void
    {
        $pageCategories = PageCategory::with(['category'])
            ->where('page_id', $sourcePageId)
            ->get();

        foreach ($pageCategories as $pageCategory) {
            $sourceCategory = $pageCategory->category;
            if (!$sourceCategory) continue;

            // Check if category with same slug exists in target site
            $targetCategory = Category::where('slug', $sourceCategory->slug)
                ->where('site_id', $targetSiteId)
                ->first();

            // Create if doesn't exist
            if (!$targetCategory) {
                $categoryData = $sourceCategory->toArray();
                unset($categoryData['id'], $categoryData['created_at'], $categoryData['updated_at']);
                $categoryData['site_id'] = $targetSiteId;

                $targetCategory = Category::create($categoryData);
            }

            // Link to new page
            PageCategory::create([
                'page_id' => $targetPageId,
                'category_id' => $targetCategory->id,
                'site_id' => $targetSiteId
            ]);
        }
    }

    /**
     * Duplicate tags from source page to target page
     */
    public function duplicateTagsToSite(int $sourcePageId, int $targetPageId, int $targetSiteId): void
    {
        $pageTags = PageTag::with(['tag'])
            ->where('page_id', $sourcePageId)
            ->get();

        foreach ($pageTags as $pageTag) {
            $sourceTag = $pageTag->tag;
            if (!$sourceTag) continue;

            // Check if tag with same slug exists in target site
            $targetTag = Tag::where('slug', $sourceTag->slug)
                ->where('site_id', $targetSiteId)
                ->first();

            // Create if doesn't exist
            if (!$targetTag) {
                $tagData = $sourceTag->toArray();
                unset($tagData['id'], $tagData['created_at'], $tagData['updated_at']);
                $tagData['site_id'] = $targetSiteId;

                $targetTag = Tag::create($tagData);
            }

            // Link to new page
            PageTag::create([
                'page_id' => $targetPageId,
                'tag_id' => $targetTag->id,
                'site_id' => $targetSiteId
            ]);
        }
    }

    /**
     * Duplicate custom fields from source page to target page
     */
    public function duplicateCustomFieldsToSite(int $sourcePageId, int $targetPageId, int $targetSiteId): void
    {
        $pageCustomFields = PageCustomField::with(['customFieldDefinition'])
            ->where('page_id', $sourcePageId)
            ->get();

        foreach ($pageCustomFields as $pageCustomField) {
            $sourceDefinition = $pageCustomField->customFieldDefinition;
            if (!$sourceDefinition) continue;

            // Check if custom field definition with same key exists in target site
            $targetDefinition = CustomFieldDefinition::where('key', $sourceDefinition->key)
                ->where('site_id', $targetSiteId)
                ->first();

            // Create if doesn't exist
            if (!$targetDefinition) {
                $definitionData = $sourceDefinition->toArray();
                unset($definitionData['id'], $definitionData['created_at'], $definitionData['updated_at']);
                $definitionData['site_id'] = $targetSiteId;
                $definitionData['name'] = $sourceDefinition->name . ' (copy)';

                $targetDefinition = CustomFieldDefinition::create($definitionData);
            }

            // Create page custom field with the value from source
            $customFieldData = $pageCustomField->toArray();
            unset($customFieldData['id'], $customFieldData['page_id'], $customFieldData['created_at'], $customFieldData['updated_at']);
            $customFieldData['page_id'] = $targetPageId;
            $customFieldData['custom_field_definition_id'] = $targetDefinition->id;
            $customFieldData['site_id'] = $targetSiteId;

            PageCustomField::create($customFieldData);
        }
    }

    public function duplicatePageAuthorsToSite(int $sourcePageId, int $targetPageId, int $targetSiteId): void
    {
        $pageAuthors = PageAuthor::with(['author'])
            ->where('page_id', $sourcePageId)
            ->orderBy('sort_order')
            ->get();

        foreach ($pageAuthors as $pageAuthor) {
            $sourceAuthor = $pageAuthor->author;
            if (!$sourceAuthor) continue;

            // Check if author with same slug exists in target site
            $targetAuthor = Author::where('slug', $sourceAuthor->slug)
                ->where('site_id', $targetSiteId)
                ->first();

            // Create if doesn't exist
            if (!$targetAuthor) {
                $authorData = $sourceAuthor->toArray();
                unset($authorData['id'], $authorData['created_at'], $authorData['updated_at']);
                $authorData['site_id'] = $targetSiteId;

                $targetAuthor = Author::create($authorData);
            }

            // Link to new page
            PageAuthor::create([
                'page_id' => $targetPageId,
                'author_id' => $targetAuthor->id,
                'role' => $pageAuthor->role,
                'sort_order' => $pageAuthor->sort_order,
                'site_id' => $targetSiteId
            ]);
        }
    }

    public function duplicateRegionSetsToSite(int $sourcePageId, int $targetPageId, int $targetSiteId): void
    {
        $pageRegionSets = PageRegionSet::with(['regionSet'])
            ->where('page_id', $sourcePageId)
            ->get();

        foreach ($pageRegionSets as $pageRegionSet) {
            $sourceRegionSet = $pageRegionSet->regionSet;
            if (!$sourceRegionSet) continue;

            // Check if region set with same slug exists in target site
            $targetRegionSet = RegionSet::where('slug', $sourceRegionSet->slug)
                ->where('site_id', $targetSiteId)
                ->first();

            // Create if doesn't exist
            if (!$targetRegionSet) {
                $regionSetData = $sourceRegionSet->toArray();
                unset($regionSetData['id'], $regionSetData['created_at'], $regionSetData['updated_at']);
                $regionSetData['site_id'] = $targetSiteId;

                $targetRegionSet = RegionSet::create($regionSetData);
            }

            // Link to new page
            PageRegionSet::create([
                'page_id' => $targetPageId,
                'region_set_id' => $targetRegionSet->id,
                'site_id' => $targetSiteId
            ]);
        }
    }

    public function duplicateTerritoriesToSite(int $sourcePageId, int $targetPageId, int $targetSiteId): void
    {
        $pageTerritories = PageTerritory::with(['territory'])
            ->where('page_id', $sourcePageId)
            ->get();

        foreach ($pageTerritories as $pageTerritory) {
            $sourceTerritory = $pageTerritory->territory;
            if (!$sourceTerritory) continue;

            // Check if territory with same code exists in target site
            $targetTerritory = Territory::where('code', $sourceTerritory->code)
                ->where('site_id', $targetSiteId)
                ->first();

            // Create if doesn't exist
            if (!$targetTerritory) {
                $territoryData = $sourceTerritory->toArray();
                unset($territoryData['id'], $territoryData['created_at'], $territoryData['updated_at']);
                $territoryData['site_id'] = $targetSiteId;

                $targetTerritory = Territory::create($territoryData);
            }

            // Link to new page
            PageTerritory::create([
                'page_id' => $targetPageId,
                'territory_id' => $targetTerritory->id,
                'site_id' => $targetSiteId
            ]);
        }
    }

    /**
     * Find territory by code in specific site
     */
    public function findTerritoryByCode(string $code, int $siteId): ?Territory
    {
        return Territory::where('code', $code)
            ->where('site_id', $siteId)
            ->first();
    }

    public function syncTags(int $pageId, int $reassignTagId)
    {
        PageTag::where('page_id', $pageId)->delete();
        return PageTag::create(['page_id' => $pageId, 'tag_id' => $reassignTagId]);
    }
}