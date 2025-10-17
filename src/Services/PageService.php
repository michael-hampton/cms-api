<?php

namespace App\Services;

use App\Framework\Database\Database;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Page;
use App\Models\PageMetadata;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Repositories\AccessRoleRepository;
use App\Repositories\BlockRepository;
use App\Repositories\PageAuthorRepository;
use App\Repositories\PageCategoryRepository;
use App\Repositories\PageCustomFieldRepository;
use App\Repositories\PageMetadataRepository;
use App\Repositories\PageRegionSetRepository;
use App\Repositories\PageRepository;
use App\Repositories\PageSeoRepository;
use App\Repositories\PageSettingsRepository;
use App\Repositories\PageSocialRepository;
use App\Repositories\PageTagRepository;
use App\Repositories\PageTerritoryRepository;
use App\Requests\PageFormValidationRules;
use DateTime;
use Exception;

class PageService
{
    private ?int $siteId;

    public function __construct(
        private PageRepository            $pageRepository,
        private BlockRepository           $blockRepository,
        private BlockParserService        $blockParserService,
        private PageMetadataRepository    $metadataRepository,
        private PageSeoRepository         $seoRepository,
        private PageSettingsRepository    $settingsRepository,
        private PageSocialRepository      $socialRepository,
        private PageCategoryRepository    $categoryRepository,
        private PageCustomFieldRepository $customFieldRepository,
        private PageTagRepository         $tagRepository,
        private AccessRoleRepository      $accessRoleRepository,
        private Database                  $database,
        private PageHistoryService        $historyService,
        private PageAuthorRepository      $pageAuthorRepository,
        private PageRegionSetRepository   $pageRegionSetRepository,
        private PageTerritoryRepository   $pageTerritoryRepository,
        ?int                              $siteId = null
    )
    {
        $this->siteId = $siteId ?? SiteContext::getId();
    }

    /**
     * Get complete page data with all relations loaded
     */
    public function getCompletePageData(int $pageId): ?Page
    {
        return $this->pageRepository->getCompletePageData($pageId);
    }

    public function createOrUpdatePageWithAllData(array $requestData, int $siteId): Page
    {
        $this->validateCompletePageData($requestData);

        return $this->database->transaction(function () use ($requestData, $siteId) {
            $pageId = $requestData['id'] ?? null;

            $mainData = $this->extractMainPageData($requestData);

            if (!empty($mainData['title']) && empty($mainData['slug'])) {
                $mainData['slug'] = Str::slug($mainData['title']);
            }

            $mainData['site_id'] = $siteId;

            $existingPage = !empty($pageId) ? $this->pageRepository->getCompletePageData($pageId) : [];

            // Store old data for comparison
            $oldPageData = null;
            $wasPublished = false;
            $isNowPublished = false;

            if (!empty($existingPage)) {
                $wasPublished = $existingPage->status === 'published';
                $oldPageData = [
                    'title' => $existingPage->title,
                    'slug' => $existingPage->slug,
                    'status' => $existingPage->status,
                    'meta_title' => $existingPage->meta_title,
                    'meta_description' => $existingPage->meta_description,
                    'blocks' => $existingPage->blocks ? $existingPage->blocks->toArray() : []
                ];
            }

            // Check if status is being changed to published
            $isNowPublished = isset($mainData['status']) && $mainData['status'] === 'published';

            // Set published_at timestamp when publishing
            if ($isNowPublished && !$wasPublished) {
                $mainData['published_at'] = date('Y-m-d H:i:s');
            }

            if (!empty($existingPage)) {
                $page = $this->pageRepository->update($pageId, $mainData);
                if (!$page) {
                    throw new Exception("Page not found");
                }

                // Log different types of updates
                if ($isNowPublished && !$wasPublished) {
                    $this->historyService->logPagePublished($page->id);
                } elseif (!$isNowPublished && $wasPublished) {
                    $this->historyService->logPageUnpublished($page->id);
                } elseif ($oldPageData) {
                    $newPageData = array_merge($oldPageData, $mainData);
                    if (!empty($requestData['blocks'])) {
                        $newPageData['blocks'] = $requestData['blocks'];
                    }
                    $this->historyService->logPageUpdated($page->id, $oldPageData, $newPageData);
                }
            } else {
                $page = $this->pageRepository->create($mainData);
                $this->historyService->logPageCreated($page);
            }

            $this->processAllFormsData($page->id, $requestData, $siteId);

            if (!empty($requestData['blocks'])) {
                $this->blockParserService->replacePageBlocks($page->id, $requestData['blocks']);
            }

            return $this->getCompletePageData($page->id);
        });
    }

    public function createPageWithAllData(array $requestData, int $siteId): Page
    {
        return $this->createOrUpdatePageWithAllData($requestData, $siteId);
    }

    public function updatePageWithAllData(int $pageId, array $requestData, int $siteId): Page
    {
        return $this->createOrUpdatePageWithAllData($requestData, $siteId);
    }

    public function deletePage(int $pageId): bool
    {
        return $this->database->transaction(function () use ($pageId) {
            $page = $this->pageRepository->find($pageId);

            if ($page) {
                $this->historyService->logPageDeleted($pageId, $page->toArray());
            }

            $this->blockRepository->deletePageBlocks($pageId);
            return $this->pageRepository->delete($pageId);
        });
    }

    // Helper methods to eliminate repetitive code
    private function processAllFormsData(int $pageId, array $requestData, int $siteId): bool
    {
        $forms = $requestData['forms'] ?? [];

        if (empty($forms)) {
            return false;
        }

        $processors = [
            'meta' => [$this, 'processMetadataForm'],
            'seo' => [$this, 'processSeoForm'],
            'settings' => [$this, 'processSettingsForm'],
            'social' => [$this, 'processSocialForm'],
        ];

        foreach ($processors as $formKey => $processor) {
            if (!empty($forms[$formKey])) {
                $processor($pageId, $forms[$formKey]);
            }
        }

        if (!empty($forms['tags'])) {
            $this->processTagsForm($pageId, $forms['tags'], $siteId);
        }

        return true;
    }

    private function extractMainPageData(array $requestData): array
    {
        $mainData = [];

        // Extract from forms.main
        if (!empty($requestData['forms']['main']['title'])) {
            $mainData['title'] = $requestData['forms']['main']['title'];
        }

        if (!empty($requestData['forms']['main']['subtitle'])) {
            $mainData['subtitle'] = $requestData['forms']['main']['subtitle'];
        }

        // Extract from forms.meta
        if (!empty($requestData['forms']['meta'])) {
            $meta = $requestData['forms']['meta'];

            if (isset($meta['slug'])) {
                $mainData['slug'] = $meta['slug'];
            }
            if (isset($meta['status'])) {
                $mainData['status'] = strtolower($meta['status']);
            }
        }

        // Extract from forms.seo (these go to main pages table too)
        if (!empty($requestData['forms']['seo'])) {
            $seo = $requestData['forms']['seo'];

            if (isset($seo['meta_title'])) {
                $mainData['meta_title'] = $seo['meta_title'];
            }
            if (isset($seo['meta_description'])) {
                $mainData['meta_description'] = $seo['meta_description'];
            }
        }

        $mainData['status'] = $requestData['status'] ?? 'draft';


        return $mainData;
    }

    private function processMetadataForm(int $pageId, array $metaForm): void
    {
        // Extract authors and contributors before processing
        $authors = $metaForm['authors'] ?? [];
        $contributors = $metaForm['contributors'] ?? [];
        $regionSets = $metaForm['region_sets'] ?? [];
        $territories = $metaForm['territories'] ?? [];

        $mapping = [
            'content_type' => 'content_type',
            'block_category' => 'block_category',
            'author' => 'author',
            'publish_date' => fn($value) => $this->formatDateTime($value),
            'expiry_date' => fn($value) => $this->formatDateTime($value),
            'visibility' => fn($value) => strtolower($value),
            'password' => 'password',
            'featured' => fn($value) => (bool)$value,
            'allow_comments' => fn($value) => (bool)$value,
            'is_reusable_block' => fn($value) => (bool)$value,
            'block_preview_image' => 'block_preview_image'
        ];

        $data = $this->mapFormData($metaForm, $mapping);
        $isCompletelyEmpty = count(array_filter($data, fn($v) => !empty($v))) === 0;

        if (!$isCompletelyEmpty) {
            $this->metadataRepository->createOrUpdate($pageId, $data);
        }

        // Sync authors and contributors
        if (!empty($authors)) {
            $this->pageAuthorRepository->syncAuthors($pageId, $authors, 'primary', $this->siteId);
        }

        if (!empty($contributors)) {
            $this->pageAuthorRepository->syncAuthors($pageId, $contributors, 'contributor', $this->siteId);
        }

        // Sync region sets
        if (!empty($regionSets)) {
            $this->pageRegionSetRepository->syncRegionSets($pageId, $regionSets, $this->siteId);
        }

        // Sync territories
        if (!empty($territories)) {
            $this->pageTerritoryRepository->syncTerritories($pageId, $territories, $this->siteId);
        }
    }

    private function processSeoForm(int $pageId, array $seoForm): void
    {
        $mapping = [
            'meta_keywords' => 'meta_keywords',
            'meta_title' => 'meta_title',
            'meta_description' => 'meta_description',
            'canonical_url' => 'canonical_url',
            'no_index' => fn($value) => (bool)$value,
            'no_follow' => fn($value) => (bool)$value,
            'og_title' => 'og_title',
            'og_description' => 'og_description',
            'og_image' => 'og_image',
            'twitter_card' => 'twitter_card',
            'schema_markup' => 'schema_markup'
        ];

        $data = $this->mapFormData($seoForm, $mapping);
        $isCompletelyEmpty = count(array_filter($data, fn($v) => !empty($v))) === 0;

        if ($isCompletelyEmpty) {
            return;
        }

        $this->seoRepository->createOrUpdate($pageId, $data);
    }

    private function processSettingsForm(int $pageId, array $settingsForm): void
    {
        $mapping = [
            'template' => 'template',
            'custom_css' => 'custom_css',
            'custom_js' => 'custom_js',
            'redirect_url' => 'redirect_url',
            'menu_order' => fn($value) => $value ? (int)$value : null,
            'parent_page' => 'parent_page',
            'latitude' => fn($value) => $value ? (float)$value : null,
            'longitude' => fn($value) => $value ? (float)$value : null,
            'address' => 'address',
            'price' => fn($value) => $value ? (float)$value : null,
            'currency' => 'currency',
            'sale_price' => fn($value) => $value ? (float)$value : null,
            'recurring' => fn($value) => (bool)$value,
            'recurring_period' => 'recurring_period'
        ];

        $data = $this->mapFormData($settingsForm, $mapping);
        $this->settingsRepository->createOrUpdate($pageId, $data);

        // Handle access roles separately
        if (isset($settingsForm['access_roles']) && is_array($settingsForm['access_roles'])) {
            $roles = array_filter($settingsForm['access_roles'], fn($r) => $r !== null);
            if (!empty($roles)) {
                $this->accessRoleRepository->syncAccessRoles($pageId, $roles);
            }
        }
    }

    private function processSocialForm(int $pageId, array $socialForm): void
    {
        $mapping = [
            'enable_sharing' => fn($value) => (bool)$value,
            'platforms' => fn($value) => is_array($value) ? json_encode($value) : $value,
            'share_text' => 'share_text',
            'share_hashtags' => 'share_hashtags',
            'share_via' => 'share_via',
            'platform_overrides' => fn($value) => $this->processPlatformOverrides($value),
            'track_shares' => fn($value) => (bool)$value,
            'track_clicks' => fn($value) => (bool)$value,
            'pixel_ids' => fn($value) => is_array($value) ? json_encode($value) : $value,
            'gtm_events' => fn($value) => (bool)$value,
            'show_follower_count' => fn($value) => (bool)$value,
            'show_share_count' => fn($value) => (bool)$value,
            'show_recent_activity' => fn($value) => (bool)$value,
            'testimonial_integration' => fn($value) => (bool)$value,
            'auto_embed_links' => fn($value) => (bool)$value,
            'embed_width' => 'embed_width',
            'embed_height' => 'embed_height',
            'lazy_load_embeds' => fn($value) => (bool)$value
        ];

        $data = $this->mapFormData($socialForm, $mapping);
        $isCompletelyEmpty = count(array_filter($data, fn($v) => !empty($v))) === 0;

        if ($isCompletelyEmpty) {
            return;
        }

        $this->socialRepository->createOrUpdate($pageId, $data);
    }

    /**
     * Process platform overrides to ensure proper format
     */
    private function processPlatformOverrides($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // If it's already a JSON string, decode and re-encode to validate
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                return null;
            }
        }

        // If it's an array, validate and sanitize
        if (is_array($value)) {
            $cleaned = [];

            foreach ($value as $platform => $override) {
                // Skip if platform is not set or override is empty
                if (empty($platform) || !is_array($override)) {
                    continue;
                }

                $cleanedOverride = [
                    'platform' => $platform
                ];

                // Only include fields that have values
                if (!empty($override['title'])) {
                    $cleanedOverride['title'] = trim($override['title']);
                }

                if (!empty($override['description'])) {
                    $cleanedOverride['description'] = trim($override['description']);
                }

                if (!empty($override['imageId'])) {
                    $cleanedOverride['imageId'] = $override['imageId'];
                }

                if (!empty($override['imageUrl'])) {
                    $cleanedOverride['imageUrl'] = $override['imageUrl'];
                }

                // Only add if at least one field is set
                if (count($cleanedOverride) > 1) {
                    $cleaned[$platform] = $cleanedOverride;
                }
            }

            return !empty($cleaned) ? json_encode($cleaned) : null;
        }

        return null;
    }

    private function processTagsForm(int $pageId, array $tagsForm, int $siteId): void
    {
        // Handle categories
        if (isset($tagsForm['categories']) && is_array($tagsForm['categories'])) {
            $this->categoryRepository->syncCategories($pageId, $tagsForm['categories'], $siteId);;
        }

        // Handle tags
        if (isset($tagsForm['tags']) && is_array($tagsForm['tags'])) {
            $this->tagRepository->syncTags($pageId, $tagsForm['tags'], $siteId);
        }

        $customFieldsData = $tagsForm['customFields'] ?? $tagsForm['custom_fields'] ?? [];

        if (!empty($customFieldsData) && is_array($customFieldsData)) {

            $customFieldsDefinitions = $this->customFieldRepository->getCustomFieldsByKeys(collect($customFieldsData)->pluck('key')->toArray());;

            $customFields = array_map(function ($field) use ($customFieldsDefinitions) {
                // Get the field definition to retrieve the ID
                $fieldDef = $customFieldsDefinitions->where('key', $field['key'])->first();

                return [
                    'custom_field_definition_id' => $fieldDef ? $fieldDef->id : null,
                    'name' => $field['key'] ?? '',
                    'key' => $field['key'] ?? '',
                    'value' => $field['value'] ?? '',
                    'type' => $field['type'] ?? 'text',
                    'options' => $field['options'] ?? null
                ];
            }, $customFieldsData);

            // Filter out any fields without a valid definition ID
            $customFields = array_filter($customFields, function ($field) {
                return !empty($field['custom_field_definition_id']);
            });

            $customFields = collect($customFields)->keyBy('custom_field_definition_id')->toArray();

            if (!empty($customFields)) {
                $this->customFieldRepository->syncCustomFields($pageId, $customFields, $siteId);
            }
        }
    }

    // Utility methods
    private function mapFormData(array $formData, array $mapping): array
    {
        $result = [];

        foreach ($mapping as $sourceKey => $target) {
            if (!isset($formData[$sourceKey])) {
                continue;
            }

            $value = $formData[$sourceKey];

            if (is_callable($target)) {
                $result[$sourceKey] = $target($value);
            } else {
                $result[$target] = $value;
            }
        }

        return $result;
    }

    private function getNestedValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    private function formatDateTime(?string $dateString): ?string
    {
        if (!$dateString) {
            return null;
        }
        return (new DateTime($dateString))->format('Y-m-d H:i:s');
    }

    private function validateCompletePageData(array $data): void
    {
        $errors = [];
        $validator = new \App\Framework\Validation\Validator($this->database);
        $validationRules = new PageFormValidationRules();

        $formValidators = [
            'main' => 'getMainFormRules',
            'meta' => 'getMetaFormRules',
            'tags' => 'getTagsFormRules',
            'social' => 'getSocialFormRules',
            'settings' => 'getSettingsFormRules',
            'seo' => 'getSeoFormRules'
        ];

        foreach ($formValidators as $formKey => $ruleMethod) {
            if (!empty($data['forms'][$formKey])) {
                $validation = $validator->validate(
                    $data['forms'][$formKey],
                    $validationRules->$ruleMethod()
                );

                if (!$validation->isValid()) {
                    $errors[$formKey] = $validation->getErrors();
                }
            }
        }

        // Validate blocks
        if (!empty($data['blocks'])) {
            $validation = $validator->validate(
                ['blocks' => $data['blocks']],
                $validationRules->getBlocksRules()
            );

            if (!$validation->isValid()) {
                $errors['blocks'] = $validation->getErrors();
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed');
        }
    }

    /**
     * Duplicate a page with all its relations
     */
    public function duplicatePage(int $pageId): ?Page
    {
        $originalPage = $this->getCompletePageData($pageId);
        if (!$originalPage) {
            return null;
        }

        return $this->database->transaction(function () use ($originalPage, $pageId) {
            $pageData = [
                'title' => $originalPage->title . ' (Copy)',
                'slug' => $originalPage->slug . '-copy-' . time(),
                'status' => 'draft',
                'meta_title' => $originalPage->meta_title,
                'meta_description' => $originalPage->meta_description,
                'site_id' => $originalPage->site_id
            ];

            $newPage = $this->pageRepository->create($pageData);

            // Duplicate all relations using repository methods
            $this->duplicatePageRelations($originalPage->id, $newPage->id);

            $this->historyService->logPageDuplicated($pageId, $newPage->id);

            return $this->getCompletePageData($newPage->id);
        });
    }

    /**
     * Duplicate all relations from source to target page
     * This method orchestrates the duplication of all page relations
     */
    private function duplicatePageRelations(int $sourcePageId, int $targetPageId): void
    {
        $relations = [
            'blocks' => 'duplicateBlocks',
            'metadata' => 'duplicateMetadata',
            'seo' => 'duplicateSeo',
            'settings' => 'duplicateSettings',
            'social' => 'duplicateSocial',
            'categories' => 'duplicateCategories',
            'tags' => 'duplicateTags',
            'customFields' => 'duplicateCustomFields',
            'accessRoles' => 'duplicateAccessRoles',
            'pageAuthors' => 'duplicatePageAuthors',
            'regionSets' => 'duplicateRegionSets',
            'territories' => 'duplicateTerritories',
        ];

        $errors = [];

        foreach ($relations as $relationType => $method) {
            try {
                $this->pageRepository->$method($sourcePageId, $targetPageId);
            } catch (\Exception $e) {
                // Log the error but continue with other relations
                error_log(sprintf(
                    'Failed to duplicate %s for page %d to %d: %s',
                    $relationType,
                    $sourcePageId,
                    $targetPageId,
                    $e->getMessage()
                ));

                $errors[$relationType] = $e->getMessage();
            }
        }

        // If critical relations failed, you might want to throw
        // For now, we'll allow partial duplication to succeed
        if (!empty($errors) && count($errors) === count($relations)) {
            throw new \Exception('Failed to duplicate any page relations: ' . json_encode($errors));
        }
    }

    public function searchPages(string $query, string $category = '', string $tag = '', string $status = 'published', $limit = null): Collection
    {
        $options = [
            'status' => $status,
            'with' => ['categories', 'tags']
        ];

        if ($limit) {
            $options['limit'] = $limit;
        }

        return $this->pageRepository->quickSearch($query, $options);
    }

    public function getPublishedPages(): Collection
    {
        return Page::with(['categories', 'tags', 'blocks', 'seo', 'settings', 'social', 'metadata'])
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findPageBySlug(string $slug): ?Page
    {
        $page = $this->pageRepository->findBySlug($slug);
        return $page ? $this->getCompletePageData($page->id) : null;
    }

    public function getFeaturedPages(?int $limit = null): Collection
    {
        return $this->pageRepository->getFeaturedPages($limit);
    }

    public function getPagesByCategory(string $category): array
    {
        // Implementation depends on whether $category is ID or slug
        return [];
    }

    public function getPagesByTag(string $tag): array
    {
        // Implementation needed
        return [];
    }

    public function bulkUpdatePages(array $pageIds, array $updateData, int $siteId): array
    {
        $results = [];
        foreach ($pageIds as $pageId) {
            try {
                $results[$pageId] = $this->updatePageWithAllData($pageId, $updateData, $siteId);
            } catch (Exception $e) {
                $results[$pageId] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * Merge source page into target page, then delete source
     *
     * @param int $sourcePageId Page to merge from (will be deleted)
     * @param int $targetPageId Page to merge into (will be kept)
     * @param array $options Merge options (e.g., which relations to merge, conflict resolution)
     * @return Page The merged target page
     * @throws Exception
     */
    public function mergePages(int $sourcePageId, int $targetPageId, array $options = []): Page
    {
        if ($sourcePageId === $targetPageId) {
            throw new \Exception("Cannot merge a page with itself");
        }

        $sourcePage = $this->getCompletePageData($sourcePageId);
        $targetPage = $this->getCompletePageData($targetPageId);

        if (!$sourcePage || !$targetPage) {
            throw new \Exception("Source or target page not found");
        }

        try {
            return $this->database->transaction(function () use ($sourcePage, $targetPage, $options) {

                // Merge relations based on strategy
                $this->mergePageRelations(
                    $sourcePage->id,
                    $targetPage->id,
                    $options['strategy'] ?? 'append'
                );

                // Optionally merge main page data
                if (!empty($options['merge_content'])) {
                    $this->mergePageContent($sourcePage, $targetPage, $options);
                }

                // Delete source page
                $this->pageRepository->delete($sourcePage->id);

                return $this->getCompletePageData($targetPage->id);
            });
        } catch (\Exception $e) {
            error_log(sprintf(
                'Failed to merge page %d into %d: %s',
                $sourcePageId,
                $targetPageId,
                $e->getMessage()
            ));

            throw new \Exception("Failed to merge pages: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Merge relations from source to target page
     *
     * @param int $sourcePageId
     * @param int $targetPageId
     * @param string $strategy 'append', 'replace', or 'keep_target'
     */
    private function mergePageRelations(int $sourcePageId, int $targetPageId, string $strategy): void
    {
        // Many-to-many relations can be appended
        $appendableRelations = ['categories', 'tags', 'accessRoles', 'regionSets', 'territories', 'pageAuthors', 'customFields'];;

        foreach ($appendableRelations as $relation) {
            $method = 'duplicate' . ucfirst($relation);
            try {
                $this->pageRepository->$method($sourcePageId, $targetPageId);
            } catch (\Exception $e) {
                error_log("Failed to merge {$relation}: {$e->getMessage()}");
                throw $e;
            }
        }

        // Blocks - append with reordering
        $this->mergeBlocks($sourcePageId, $targetPageId, $strategy);

        // One-to-one relations - strategy dependent
        if ($strategy === 'replace') {
            $this->replaceOneToOneRelations($sourcePageId, $targetPageId);
        } elseif ($strategy === 'append') {
            // For settings/metadata, we might want to merge specific fields
            $this->mergeSettings($sourcePageId, $targetPageId);
        }

        // 'keep_target' strategy: do nothing for one-to-one relations

        // Custom fields - merge or append
        $this->mergeCustomFields($sourcePageId, $targetPageId);
    }

    /**
     * Merge blocks from source to target, reordering as needed
     */
    private function mergeBlocks(int $sourcePageId, int $targetPageId, string $strategy): void
    {
        if ($strategy === 'replace') {
            // Delete target blocks, copy source blocks
            $this->blockRepository->deletePageBlocks($targetPageId);
            $this->pageRepository->duplicateBlocks($sourcePageId, $targetPageId);
        } else {

            // Append: get max order from target, then copy source blocks with offset
            $maxOrder = $this->blockRepository->getMaxOrder($targetPageId) ?? 0;

            // Use Block model instead of raw query
            $sourceBlocks = $this->blockRepository->getBlocksForPage($sourcePageId);

            foreach ($sourceBlocks as $block) {
                $this->blockRepository->create([
                    'page_id' => $targetPageId,
                    'type' => $block->type,
                    'data' => $block->data,
                    'order' => $maxOrder + $block->order
                ]);
            }
        }
    }

    /**
     * Replace target's one-to-one relations with source's
     */
    private function replaceOneToOneRelations(int $sourcePageId, int $targetPageId): void
    {
        $relations = [
            'Metadata' => PageMetadata::class,
            'Seo' => PageSeo::class,
            'Settings' => PageSettings::class,
            'Social' => PageSocial::class
        ];

        foreach ($relations as $relation => $modelClass) {
            // Delete existing using model
            $modelClass::where('page_id', $targetPageId)->delete();

            // Copy from source
            $method = 'duplicate' . $relation;
            $this->pageRepository->$method($sourcePageId, $targetPageId);
        }
    }

    /**
     * Merge settings intelligently (e.g., keep non-null values)
     */
    private function mergeSettings(int $sourcePageId, int $targetPageId): void
    {
        $sourceSettings = PageSettings::where('page_id', $sourcePageId)->first();

        if (!$sourceSettings) {
            return;
        }

        $targetSettings = PageSettings::where('page_id', $targetPageId)->first();

        if (!$targetSettings) {
            // No target settings, just copy
            $this->pageRepository->duplicateSettings($sourcePageId, $targetPageId);
            return;
        }

        // Merge: prefer target values, but fill in gaps from source
        $sourceData = $sourceSettings->toArray();
        $targetData = $targetSettings->toArray();

        $merged = $targetData;
        foreach ($sourceData as $key => $value) {
            if ($key !== 'id' && $key !== 'page_id' && empty($merged[$key]) && !empty($value)) {
                $merged[$key] = $value;
            }
        }

        unset($merged['id']);
        $merged['page_id'] = $targetPageId;

        PageSettings::where('page_id', $targetPageId)->update($merged);
    }

    /**
     * Merge custom fields (append unique keys)
     */
    private function mergeCustomFields(int $sourcePageId, int $targetPageId): void
    {
        $sourceFields = $this->customFieldRepository->getCustomFieldsForPage($sourcePageId);

        $customFields = $this->customFieldRepository->getCustomFieldsForPage($targetPageId);

        $existingKeys = $customFields->pluck('custom_field_definition_id')->all();

        foreach ($sourceFields as $field) {
            // Only add if key doesn't exist on target
            if (!in_array($field->custom_field_definition_id, $existingKeys)) {
                $data = $field->toArray();
                unset($data['id']);
                $data['page_id'] = $targetPageId;
                $this->customFieldRepository->create($data);
            }
        }
    }

    /**
     * Optionally merge content from source into target
     */
    private function mergePageContent(Page $sourcePage, Page $targetPage, array $options): void
    {
        $updates = [];

        if (!empty($options['append_title'])) {
            $updates['title'] = $targetPage->title . ' & ' . $sourcePage->title;
        }

        if (!empty($options['merge_descriptions'])) {
            $updates['meta_description'] = trim(
                ($targetPage->meta_description ?? '') . ' ' . ($sourcePage->meta_description ?? '')
            );
        }

        if (!empty($updates)) {
            $this->pageRepository->update($targetPage->id, $updates);
        }
    }

    public function publishPage(int $pageId): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new Exception("Page not found");
        }

        if ($page->status === 'published') {
            throw new Exception("Page is already published");
        }

        $page = $this->pageRepository->update($pageId, [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s')
        ]);

        $this->historyService->logPagePublished($pageId);

        return $page;
    }

    public function unpublishPage(int $pageId): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new Exception("Page not found");
        }

        if ($page->status !== 'published') {
            throw new Exception("Page is not published");
        }

        $page = $this->pageRepository->update($pageId, [
            'status' => 'draft'
        ]);

        $this->historyService->logPageUnpublished($pageId);

        return $page;
    }

    /**
     * Clone page to a different site
     *
     * @param int $pageId Source page ID
     * @param int $targetSiteId Target site ID
     * @param string|null $newTitle Optional new title
     * @return Page The cloned page in the target site
     * @throws Exception
     */
    public function clonePageToSite(int $pageId, int $targetSiteId, ?string $newTitle = null): Page
    {
        $sourcePage = $this->getCompletePageData($pageId);
        if (!$sourcePage) {
            throw new \Exception("Source page not found");
        }

        if ($sourcePage->site_id === $targetSiteId) {
            throw new \Exception("Source and target site cannot be the same");
        }

        return $this->database->transaction(function () use ($sourcePage, $targetSiteId, $newTitle) {
            $pageData = [
                'title' => $newTitle ?? ($sourcePage->title . ' (Copy)'),
                'slug' => $this->generateUniqueSlug($sourcePage->slug, $targetSiteId),
                'status' => 'draft',
                'meta_title' => $sourcePage->meta_title,
                'meta_description' => $sourcePage->meta_description,
                'subtitle' => $sourcePage->subtitle,
                'site_id' => $targetSiteId
            ];

            $newPage = $this->pageRepository->create($pageData);

            // Clone all relations to the new site
            $this->clonePageRelationsToSite($sourcePage->id, $newPage->id, $targetSiteId);

            $this->historyService->logPageClonedToSite($sourcePage->id, $newPage->id, $targetSiteId);

            return $this->getCompletePageData($newPage->id);
        });
    }

    /**
     * Generate a unique slug for the target site
     */
    private function generateUniqueSlug(string $baseSlug, int $targetSiteId): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while ($this->pageRepository->slugExistsInSite($slug, $targetSiteId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Duplicate all relations from source to target page
     * This method orchestrates the duplication of all page relations
     */
    private function clonePageRelationsToSite(int $sourcePageId, int $targetPageId, int $targetSiteId): void
    {
        $relations = [
            'blocks' => 'duplicateBlocks',
            'metadata' => 'duplicateMetadata',
            'seo' => 'duplicateSeo',
            'settings' => 'duplicateSettings',
            'social' => 'duplicateSocial',
            'categories' => 'duplicateCategoriesToSite',
            'tags' => 'duplicateTagsToSite',
            'customFields' => 'duplicateCustomFieldsToSite',
            'accessRoles' => 'duplicateAccessRoles',
            'pageAuthors' => 'duplicatePageAuthorsToSite',
            'regionSets' => 'duplicateRegionSetsToSite',
            'territories' => 'duplicateTerritoriesToSite',
        ];

        $errors = [];

        foreach ($relations as $relationType => $method) {
            try {
                $this->pageRepository->$method($sourcePageId, $targetPageId, $targetSiteId);
            } catch (\Exception $e) {
                // Log the error but continue with other relations
                error_log(sprintf(
                    'Failed to clone %s for page %d to site %d: %s',
                    $relationType,
                    $sourcePageId,
                    $targetPageId,
                    $e->getMessage()
                ));

                $errors[$relationType] = $e->getMessage();
            }
        }

        // If critical relations failed, you might want to throw
        // For now, we'll allow partial duplication to succeed
        if (!empty($errors) && count($errors) === count($relations)) {
            throw new \Exception('Failed to duplicate any page relations: ' . json_encode($errors));
        }
    }
}