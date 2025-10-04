<?php

namespace App\Services;

use App\Framework\Database\Database;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Page;
use App\Repositories\AccessRoleRepository;
use App\Repositories\BlockRepository;
use App\Repositories\PageCategoryRepository;
use App\Repositories\PageCustomFieldRepository;
use App\Repositories\PageMetadataRepository;
use App\Repositories\PageRepository;
use App\Repositories\PageSeoRepository;
use App\Repositories\PageSettingsRepository;
use App\Repositories\PageSocialRepository;
use App\Repositories\PageTagRepository;
use App\Requests\PageFormValidationRules;
use DateTime;
use Exception;

class PageService
{
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
        private Database                  $database
    )
    {
    }

    /**
     * Get complete page data with all relations loaded
     */
    public function getCompletePageData(int $pageId): ?Page
    {
        return $this->pageRepository->getCompletePageData($pageId);
    }

    public function createOrUpdatePageWithAllData(array $requestData): Page
    {
        $this->validateCompletePageData($requestData);

        return $this->database->transaction(function () use ($requestData) {
            $pageId = $requestData['id'] ?? null;
            $mainData = $this->extractMainPageData($requestData);

            if (!empty($mainData['title']) && empty($mainData['slug'])) {
                $mainData['slug'] = Str::slug($mainData['title']);
            }

            if ($pageId) {
                $page = $this->pageRepository->update($pageId, $mainData);
                if (!$page) {
                    throw new Exception("Page not found");
                }
            } else {
                $page = Page::create($mainData);
            }

            $this->processAllFormsData($page->id, $requestData);

            if (!empty($requestData['blocks'])) {
                $this->blockParserService->replacePageBlocks($page->id, $requestData['blocks']);
            }

            return $this->getCompletePageData($page->id);
        });
    }

    public function createPageWithAllData(array $requestData): Page
    {
        unset($requestData['id']);
        return $this->createOrUpdatePageWithAllData($requestData);
    }

    public function updatePageWithAllData(int $pageId, array $requestData): Page
    {
        $requestData['id'] = $pageId;
        return $this->createOrUpdatePageWithAllData($requestData);
    }

    public function deletePage(int $pageId): bool
    {
        return $this->database->transaction(function () use ($pageId) {
            $this->blockRepository->deletePageBlocks($pageId);
            return $this->pageRepository->delete($pageId);
        });
    }

    // Helper methods to eliminate repetitive code
    private function processAllFormsData(int $pageId, array $requestData): bool
    {
        $forms = $requestData['forms'] ?? [];

        if(empty($forms)) {
            return false;
        }

        $processors = [
            'meta' => [$this, 'processMetadataForm'],
            'seo' => [$this, 'processSeoForm'],
            'settings' => [$this, 'processSettingsForm'],
            'social' => [$this, 'processSocialForm'],
            'tags' => [$this, 'processTagsForm'],
        ];

        foreach ($processors as $formKey => $processor) {
            if (!empty($forms[$formKey])) {
                $processor($pageId, $forms[$formKey]);
            }
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

        return $mainData;
    }

    private function processMetadataForm(int $pageId, array $metaForm): void
    {
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
        $this->metadataRepository->createOrUpdate($pageId, $data);
    }

    private function processSeoForm(int $pageId, array $seoForm): void
    {
        $mapping = [
            'meta_keywords' => 'meta_keywords',
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
        $this->socialRepository->createOrUpdate($pageId, $data);
    }

    /**
     * Process platform overrides to ensure proper format
     */
    private function processPlatformOverrides($value): ?string //todo this needs a test
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

    private function processTagsForm(int $pageId, array $tagsForm): void
    {
        // Handle categories
        if (isset($tagsForm['categories']) && is_array($tagsForm['categories'])) {
            $this->categoryRepository->syncCategories($pageId, $tagsForm['categories']);
        }

        // Handle tags
        if (isset($tagsForm['tags']) && is_array($tagsForm['tags'])) {
            $this->tagRepository->syncTags($pageId, $tagsForm['tags']);
        }

        // Handle custom fields - convert from [{key, value, type}] to expected format
        if (isset($tagsForm['customFields']) && is_array($tagsForm['customFields'])) {
            $customFields = array_map(function ($field) {
                return [
                    'name' => $field['key'] ?? '',
                    'key' => $field['key'] ?? '',
                    'value' => $field['value'] ?? '',
                    'type' => $field['type'] ?? 'text',
                    'options' => $field['options'] ?? null
                ];
            }, $tagsForm['customFields']);

            $this->customFieldRepository->syncCustomFields($pageId, $customFields);
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

    // Business logic methods
    public function duplicatePage(int $pageId): ?Page
    {
        $originalPage = $this->getCompletePageData($pageId);
        if (!$originalPage) {
            return null;
        }

        return $this->database->transaction(function () use ($originalPage) {
            $pageData = [
                'title' => $originalPage->title . ' (Copy)',
                'slug' => $originalPage->slug . '-copy-' . time(),
                'status' => 'draft',
                'meta_title' => $originalPage->meta_title,
                'meta_description' => $originalPage->meta_description
            ];

            $newPage = $this->pageRepository->create($pageData);

            if ($originalPage->relationLoaded('blocks')) {

                foreach ($originalPage->blocks as $block) {

                    $this->blockRepository->create([
                        'page_id' => $newPage->id,
                        'type' => $block->type,
                        'data' => $block->data,
                        'order' => $block->order
                    ]);
                }
            }

            return $this->getCompletePageData($newPage->id);
        });
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

    public function bulkUpdatePages(array $pageIds, array $updateData): array
    {
        $results = [];
        foreach ($pageIds as $pageId) {
            try {
                $results[$pageId] = $this->updatePageWithAllData($pageId, $updateData);
            } catch (Exception $e) {
                $results[$pageId] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }
}