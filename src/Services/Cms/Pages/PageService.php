<?php

namespace App\Services\Cms\Pages;

use App\Enums\Pages\PageStatus;
use App\Events\Cms\ContentApproved;
use App\Events\Cms\ContentHeld;
use App\Events\Cms\ContentRejected;
use App\Events\Cms\ContentSubmittedForApproval;
use App\Framework\Database\Database;
use App\Framework\Exceptions\BlockParserNotFoundException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Framework\Validation\Validator;
use App\Models\Model;
use App\Models\Page;
use App\Repositories\Cms\AccessRoleRepository;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\Pages\PageAuthorRepository;
use App\Repositories\Cms\Pages\PageCategoryRepository;
use App\Repositories\Cms\Pages\PageCustomFieldRepository;
use App\Repositories\Cms\Pages\PageMetadataRepository;
use App\Repositories\Cms\Pages\PageProductRepository;
use App\Repositories\Cms\Pages\PageRegionSetRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\Pages\PageSeoRepository;
use App\Repositories\Cms\Pages\PageSettingsRepository;
use App\Repositories\Cms\Pages\PageSocialRepository;
use App\Repositories\Cms\Pages\PageTagRepository;
use App\Repositories\Cms\Pages\PageTerritoryRepository;
use App\Requests\PageFormValidationRules;
use App\Services\PublicContent\PageReviewDataFactory;
use DateTime;
use Exception;

class PageService
{
    private ?int $siteId;

    public function __construct(
        private readonly PageRepository               $pageRepository,
        private readonly BlockRepository              $blockRepository,
        private readonly BlockParserService           $blockParserService,
        private readonly PageMetadataRepository       $metadataRepository,
        private readonly PageSeoRepository            $seoRepository,
        private readonly PageSettingsRepository       $settingsRepository,
        private readonly PageSocialRepository         $socialRepository,
        private readonly PageCategoryRepository       $categoryRepository,
        private readonly PageCustomFieldRepository    $customFieldRepository,
        private readonly PageTagRepository            $tagRepository,
        private readonly AccessRoleRepository         $accessRoleRepository,
        private readonly Database                     $database,
        private readonly PageHistoryService           $historyService,
        private readonly PageAuthorRepository         $pageAuthorRepository,
        private readonly PageRegionSetRepository      $pageRegionSetRepository,
        private readonly PageTerritoryRepository      $pageTerritoryRepository,
        private readonly PageProductRepository        $pageProductRepository,
        private readonly PremiumPageApprovalService   $premiumApprovalService,
        private readonly FirstEditorialChangeReporter $firstEditorialChangeReporter,
        private readonly PageReviewDataFactory        $reviewDataFactory,
        ?int                                          $siteId = null,

    )
    {
        $this->siteId = $siteId ?? SiteContext::getId();
    }

    public function pendingReviewForSite(int $siteId): Collection
    {
        return $this->pageRepository
            ->query()
            ->where('site_id', $siteId)
            ->where('status', PageStatus::WAITING_APPROVAL->value)
            ->whereNotNull('contributor_id')
            ->orderBy('submitted_at')
            ->get();
    }

    public function createPageWithAllData(array $requestData, int $siteId): Page
    {
        // Check if the page requires approval and is being created as published
        $status = $requestData['status'] ?? $requestData['forms']['meta']['status'] ?? 'draft';
        $requiresApproval = $requestData['requires_approval'] ?? false;

        // Convert status to lowercase for comparison
        $status = strtolower($status);

        if (!isset($requestData['status'])) {
            $requestData['status'] = $status;
        }

        // If trying to publish and requires approval, force to waiting_approval
        if ($status === 'published' && $requiresApproval) {
            $requestData['status'] = PageStatus::WAITING_APPROVAL->value;

            if (isset($requestData['forms']['meta'])) {
                $requestData['forms']['meta']['status'] = PageStatus::WAITING_APPROVAL->value;
            }
        }

        $page = $this->createOrUpdatePageWithAllData($requestData, $siteId);

        // Log waiting approval if that's the status
        if ($page->status === PageStatus::WAITING_APPROVAL->value) {
            $this->historyService->logPageWaitingApproval($page);
            if (empty($requestData['suppress_workflow_notifications'])) {
                $this->dispatchSubmittedForApproval($page, (int)($requestData['user_id'] ?? $requestData['contributor_id'] ?? $requestData['owner_id'] ?? 0));
            }
        }

        return $page;
    }

    public function createOrUpdatePageWithAllData(array $requestData, int $siteId): Page
    {
        $this->validateCompletePageData($requestData);

        return $this->database->transaction(function () use ($requestData, $siteId) {
            $pageId = $requestData['id'] ?? null;

            $mainData = $this->extractMainPageData($requestData);
            $mainData['page_type'] = $mainData['page_type'] ?? 'content';

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
                    $pageHistory = $this->historyService->logPageUpdated($page->id, $oldPageData, $newPageData);

                    if ($pageHistory) {
                        $this->firstEditorialChangeReporter->reportIfNeeded(
                            page: $existingPage,
                            actorId: $pageHistory->user_id,
                            pageHistoryId: (int)$pageHistory->id,
                        );
                    }
                }
            } else {
                $page = $this->pageRepository->create($mainData);
                $this->historyService->logPageCreated($page);
            }

            $this->processAllFormsData($page->id, $requestData, $siteId);

            if (!empty($requestData['blocks'])) {
                $this->validateAndReplaceBlocks($page->id, $requestData['blocks']);
            }

            if (!empty($requestData['gallery_slides'])) {
                $this->validateGallerySlides($page->id, $requestData['gallery_slides']);
            }

            return $this->getCompletePageData($page->id);
        });
    }

    private function validateCompletePageData(array $data): void
    {
        $errors = [];
        $validator = new Validator($this->database);
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

    private function extractMainPageData(array $requestData): array
    {
        $mainData = ['status' => $requestData['status'] ?? 'draft'];

        // Define field mappings for cleaner extraction
        $fieldMappings = [
            'forms.main.title' => 'title',
            'forms.main.subtitle' => 'subtitle',
            'forms.main.content' => 'content',
            'forms.main.owner' => 'owner_id',
            'hero_type' => 'hero_type',
            'brief_id' => 'brief_id',
            'hero_image_id' => 'hero_image_id',
            'hero_video_url' => 'hero_video_url',
            'forms.meta.slug' => 'slug',
            'forms.meta.custom_route' => 'custom_route',
            'status' => 'status',
            'forms.seo.meta_title' => 'meta_title',
            'forms.seo.meta_description' => 'meta_description',
            'forms.listing.synopsis' => 'listing_synopsis',
            'forms.listing.listingTitle' => 'listing_title',
            'forms.listing.dekLabel' => 'listing_label',
            'forms.listing.imageId' => 'listing_image_id',
            'forms.listing.useAsHero' => 'listing_use_as_hero',
            'forms.meta.content_type' => 'page_type',
            'requires_approval' => 'requires_approval',
            'contributor_id' => 'contributor_id',
            'is_public_contribution' => 'is_public_contribution',
            'is_paid' => 'is_paid',
            'price' => 'price',
            'forms.reviews.data' => 'review_data',
        ];

        foreach ($fieldMappings as $path => $field) {
            $value = $this->getNestedValue($requestData, $path);
            if ($value !== null) {
                if ($field === 'status') {
                    $mainData[$field] = strtolower($value);
                } elseif ($field === 'listing_use_as_hero') {
                    $mainData[$field] = (bool)$value;
                } elseif ($field === 'review_data') {
                    $mainData[$field] = $this->reviewDataFactory->fromArray((array)$value)->toArray();
                } else {
                    $mainData[$field] = $value;
                }
            }
        }

        // Handle JSON fields
        if (!empty($requestData['forms']['cropOverrides'])) {
            $mainData['crop_overrides'] = json_encode($requestData['forms']['cropOverrides']);
        }

        if (!empty($requestData['resolved_images'])) {
            $mainData['resolved_images'] = json_encode($requestData['resolved_images']);
        }

        // Auto-generate slug if title exists but slug doesn't
        if (!empty($mainData['title']) && empty($mainData['slug'])) {
            $mainData['slug'] = Str::slug($mainData['title']);
        }

        if (!empty($requestData['gallery_slides'])) {
            $mainData['gallery_slides'] = json_encode($requestData['gallery_slides']);
        }

        if (!empty($requestData['zones'])) {
            $mainData['zones'] = is_string($requestData['zones'])
                ? $requestData['zones']
                : json_encode($requestData['zones']);
        }

        if (array_key_exists('custom_route', $mainData)) {
            $mainData['custom_route'] = $this->normaliseCustomRoute($mainData['custom_route']);
        }

        return $mainData;
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

    private function normaliseCustomRoute(mixed $route): ?string
    {
        if (!is_string($route)) {
            return null;
        }

        $route = trim(rawurldecode($route));
        $route = trim($route, '/');

        if ($route === '') {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', $route),
            static fn(string $segment): bool => trim($segment) !== ''
        ));

        return implode('/', array_map(
            static fn(string $segment): string => trim($segment),
            $segments
        ));
    }

    /**
     * Get complete page data with all relations loaded
     */
    public function getCompletePageData(int $pageId): ?Page
    {
        return $this->pageRepository->getCompletePageData($pageId);
    }

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

        if (isset($tagsForm['products']) && is_array($tagsForm['products'])) {
            $this->pageProductRepository->syncProducts($pageId, $tagsForm['products'], $siteId);
        }


        $customFieldsData = $tagsForm['customFields']
            ?? $tagsForm['custom_fields']
            ?? [];

        if (empty($customFieldsData)) {
            return;
        }

        $customFieldsCollection = collect($customFieldsData);

        /**
         * Get unique definition keys
         */
        $definitionKeys = $customFieldsCollection
            ->pluck('customFieldDefinition.key')
            ->filter()
            ->unique()
            ->toArray();

        /**
         * Fetch definitions indexed by key
         */
        $customFieldDefinitions = $this->customFieldRepository
            ->getCustomFieldsByKeys($definitionKeys, $siteId);

        $keyedCollection = new Collection(
            array_column($customFieldDefinitions->toArray(), null, 'key')
        );

        /**
         * Build payload
         */
        $customFields = $customFieldsCollection
            ->map(function ($field) use ($customFieldDefinitions, $keyedCollection) {
                $definitionKey = $field['customFieldDefinition']['key'] ?? null;

                $definition = $keyedCollection->get($definitionKey);

                if (!$definition) {
                    return null;
                }

                return [
                    'custom_field_definition_id' => $definition['id'],
                    'name' => $field['key'] ?? '',
                    'key' => $field['key'] ?? '',
                    'default_value' => $field['value'] ?? '',  // Changed from 'value' to 'default_value'
                    'type' => $field['type'] ?? 'text',
                    'options' => $field['options'] ?? null,
                ];
            })
            ->filter() // removes nulls
            ->keyBy('custom_field_definition_id')
            ->whereNotEmpty('default_value')  // Changed from 'value' to 'default_value'
            ->toArray();

        if (!empty($customFields)) {
            $this->customFieldRepository->syncCustomFields($pageId, $customFields, $siteId);
        }

    }

    /**
     * Validate and replace page blocks using BlockParserService
     */
    private function validateAndReplaceBlocks(int $pageId, array $blocks): void
    {
        try {
            // Use BlockParserService to validate and parse blocks
            $this->blockParserService->replacePageBlocks($pageId, $blocks);
        } catch (ValidationException $e) {
            throw new ValidationException('Block validation failed', $e->getErrors());
        } catch (Exception $e) {
            throw new Exception("Failed to process blocks: {$e->getMessage()}");
        }
    }

    /**
     * Validate gallery slides and their blocks
     */
    private function validateGallerySlides(int $pageId, array $slides): void
    {
        $errors = [];

        foreach ($slides as $slideIndex => $slide) {
            // Validate slide structure
            if (empty($slide['image_id'])) {
                $errors["slide_{$slideIndex}"] = ['image_id' => 'Slide must have an image'];
            }

            if (empty($slide['title'])) {
                $errors["slide_{$slideIndex}"] = ['title' => 'Slide must have a title'];
            }

            // Validate blocks within the slide
            if (!empty($slide['blocks'])) {
                try {
                    // Create a temporary validation - we don't actually save these to the blocks table
                    // since they're stored as JSON in gallery_slides
                    foreach ($slide['blocks'] as $blockIndex => $blockData) {
                        $this->validateBlockStructure($blockData, $slideIndex, $blockIndex);
                    }
                } catch (ValidationException $e) {
                    $errors["slide_{$slideIndex}_blocks"] = $e->getErrors();
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Gallery slide validation failed', $errors);
        }
    }

    /**
     * Validate individual block structure without saving
     */
    private function validateBlockStructure(array $blockData, int $slideIndex, int $blockIndex): void
    {
        try {
            // Use BlockParserService's public validateBlock method
            $this->blockParserService->validateBlock($blockData);
        } catch (ValidationException $e) {
            throw new ValidationException(
                "Validation failed for block at slide {$slideIndex}, position {$blockIndex}",
                $e->getErrors()
            );
        } catch (BlockParserNotFoundException $e) {
            throw new ValidationException(
                "Invalid block type '{$blockData['type']}' at slide {$slideIndex}, position {$blockIndex}"
            );
        }
    }

    private function dispatchSubmittedForApproval(Page $page, int $actorId): void
    {
        event(new ContentSubmittedForApproval(
            contentType: 'pages',
            contentId: (int)$page->id,
            siteId: (int)$page->site_id,
            actorId: $actorId,
            title: (string)$page->title,
            ownerId: $this->pageOwnerId($page),
        ));
    }

    private function pageOwnerId(Page $page): ?int
    {
        foreach (['contributor_id', 'owner_id', 'created_by', 'author_id'] as $field) {
            if (!empty($page->$field)) {
                return (int)$page->$field;
            }
        }

        return null;
    }

    public function updatePageWithAllData(int $pageId, array $requestData, int $siteId, ?Model $page = null): Page
    {
        $page = $page ?? $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \Exception("Page not found");
        }

        // Extract new status if being changed
        $newStatus = $requestData['status'] ?? $requestData['forms']['meta']['status'] ?? null;

        if ($newStatus) {
            $newStatus = strtolower($newStatus);

            // Validate status transition
            if (strtolower($page->status) !== $newStatus && !$page->canTransitionTo($newStatus)) {
                throw new \Exception("Cannot change status from {$page->status} to {$newStatus}");
            }

            // Handle publishing with approval workflow
            if ($newStatus === strtolower(PageStatus::PUBLISHED->value)) {
                $this->handlePublishAttempt($page, $requestData);
            }
        }

        $wasWaitingApproval = $page->status === PageStatus::WAITING_APPROVAL->value;
        $updatedPage = $this->createOrUpdatePageWithAllData($requestData, $siteId);

        if (!$wasWaitingApproval && $updatedPage->status === PageStatus::WAITING_APPROVAL->value && empty($requestData['suppress_workflow_notifications'])) {
            $this->dispatchSubmittedForApproval($updatedPage, (int)($requestData['user_id'] ?? $requestData['contributor_id'] ?? $requestData['owner_id'] ?? 0));
        }

        return $updatedPage;
    }

    /**
     * Handle publish attempt - check if approval is needed
     */
    private function handlePublishAttempt(Page $page, array &$requestData): void
    {
        // If page requires approval and is not approved yet
        if ($page->requiresApproval() && !$page->isApproved()) {

            // Change status to waiting_approval instead
            $requestData['status'] = PageStatus::WAITING_APPROVAL->value;

            if (isset($requestData['forms']['meta'])) {
                $requestData['forms']['meta']['status'] = PageStatus::WAITING_APPROVAL->value;
            }

            // Log that approval is needed
            $this->historyService->logPageWaitingApproval($page);
        }
    }

    /**
     * Approve a page for publishing
     */
    public function approvePage(int $pageId, int $userId): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \InvalidArgumentException("Page [{$pageId}] not found.");
        }

        if (!$page->isWaitingApproval()) {
            throw new \InvalidArgumentException("Article [{$pageId}] is not awaiting approval (status: {$page->status}).");
        }

        $approvedPage = $this->database->transaction(function () use ($page, $userId) {
            // Mark as approved
            $page->approve($userId);

            // Change status to published
            $updatedPage = $this->pageRepository->update($page->id, [
                'status' => PageStatus::PUBLISHED->value,
                'published_at' => date('Y-m-d H:i:s')
            ]);

            // Log approval
            $this->historyService->logPageApproved($page, $userId);
            $this->historyService->logPagePublished($page->id);

            return $this->getCompletePageData($updatedPage->id);
        });

        event(new ContentApproved(
            contentType: 'pages',
            contentId: (int)$approvedPage->id,
            siteId: (int)$approvedPage->site_id,
            actorId: $userId,
            title: (string)$approvedPage->title,
            ownerId: $this->pageOwnerId($approvedPage),
        ));

        return $approvedPage;
    }

    public function submitPageForReview(int $pageId, int $contributorId): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \InvalidArgumentException("Page [{$pageId}] not found.");
        }

        if ((int)$page->contributor_id !== $contributorId) {
            throw new \InvalidArgumentException("Page [{$pageId}] does not belong to contributor [{$contributorId}].");
        }

        if (!in_array($page->status, [PageStatus::DRAFT->value, PageStatus::ON_HOLD->value], true)) {
            throw new \InvalidArgumentException(
                "Article [{$pageId}] cannot be submitted from status [{$page->status}]."
            );
        }

        $submittedPage = $this->database->transaction(function () use ($page, $contributorId) {
            $updatedPage = $this->pageRepository->update($page->id, [
                'status' => PageStatus::WAITING_APPROVAL->value,
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);

            $this->historyService->logPageWaitingApproval($page);

            return $this->getCompletePageData($updatedPage->id);
        });

        $this->dispatchSubmittedForApproval($submittedPage, $contributorId);

        return $submittedPage;
    }

    // Helper methods to eliminate repetitive code

    public function resubmitPageForReview(int $pageId, int $contributorId): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \InvalidArgumentException("Page [{$pageId}] not found.");
        }

        if ((int)$page->contributor_id !== $contributorId) {
            throw new \InvalidArgumentException("Page [{$pageId}] does not belong to contributor [{$contributorId}].");
        }

        if ($page->status !== PageStatus::REJECTED->value) {
            throw new \InvalidArgumentException(
                "Article [{$pageId}] cannot be resubmitted from status [{$page->status}]."
            );
        }

        $submittedPage = $this->database->transaction(function () use ($page, $contributorId) {
            $updatedPage = $this->pageRepository->update($page->id, [
                'status' => PageStatus::WAITING_APPROVAL->value,
                'submitted_at' => date('Y-m-d H:i:s'),
                'resubmission_count' => ((int)$page->resubmission_count) + 1,
            ]);

            $this->historyService->logPageWaitingApproval($page);

            return $this->getCompletePageData($updatedPage->id);
        });

        $this->dispatchSubmittedForApproval($submittedPage, $contributorId);

        return $submittedPage;
    }

    /**
     * Reject approval request
     */
    public function rejectPage(int $pageId, int $userId, ?string $reason = null, ?string $notes = null): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \InvalidArgumentException("Page [{$pageId}] not found.");
        }

        if (!$page->isWaitingApproval()) {
            throw new \InvalidArgumentException("Article [{$pageId}] is not awaiting approval (status: {$page->status}).");
        }

        $rejectedPage = $this->database->transaction(function () use ($page, $userId, $reason, $notes) {
            // Remove approval if it was there
            $page->removeApproval();

            // Keep rejected pages in an explicit rejected workflow state.
            $updatedPage = $this->pageRepository->update($page->id, [
                'status' => PageStatus::REJECTED->value,
                'rejected_by' => $userId,
                'rejected_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => $reason,
                'rejection_notes' => $notes,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            // Log rejection
            $this->historyService->logPageRejected($page, $userId, $reason);

            return $this->getCompletePageData($updatedPage->id);
        });

        event(new ContentRejected(
            contentType: 'pages',
            contentId: (int)$rejectedPage->id,
            siteId: (int)$rejectedPage->site_id,
            actorId: $userId,
            title: (string)$rejectedPage->title,
            ownerId: $this->pageOwnerId($rejectedPage),
            reason: $reason,
        ));

        return $rejectedPage;
    }

    /**
     * Set page to on hold
     */
    public function putPageOnHold(int $pageId, int $userId, ?string $reason = null): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \Exception("Page not found");
        }

        if (!$page->canTransitionTo(Pagestatus::ON_HOLD)) {
            throw new \Exception("Cannot put page on hold from current status");
        }

        $heldPage = $this->database->transaction(function () use ($page, $userId, $reason) {
            $updatedPage = $this->pageRepository->update($page->id, [
                'status' => Pagestatus::ON_HOLD->value,
            ]);

            $this->historyService->logPagePutOnHold($page, $userId, $reason);

            return $this->getCompletePageData($updatedPage->id);
        });

        event(new ContentHeld(
            contentType: 'pages',
            contentId: (int)$heldPage->id,
            siteId: (int)$heldPage->site_id,
            actorId: $userId,
            title: (string)$heldPage->title,
            ownerId: $this->pageOwnerId($heldPage),
            reason: $reason,
        ));

        return $heldPage;
    }

    /**
     * Set page to private
     */
    public function makePagePrivate(int $pageId, int $userId): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \Exception("Page not found");
        }

        if (!$page->canTransitionTo(Pagestatus::PRIVATE)) {
            throw new \Exception("Cannot make page private from current status");
        }

        return $this->database->transaction(function () use ($page, $userId) {
            $updatedPage = $this->pageRepository->update($page->id, [
                'status' => Pagestatus::PRIVATE->value,
            ]);

            $this->historyService->logPageMadePrivate($page, $userId);

            return $this->getCompletePageData($updatedPage->id);
        });
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

    // Utility methods

    public function getPagesByTag(string $tag): array
    {
        // Implementation needed
        return [];
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

    public function unpublishPage(int $id, int $userId, ?string $redirectUrl = null): Page
    {
        $page = $this->pageRepository->find($id);

        if (!$page) {
            throw new Exception("Page not found");
        }

        if ($page->status !== 'published') {
            throw new Exception("Page is not published");
        }

        $this->database->transaction(function () use ($page, $userId, $redirectUrl) {
            // Update page status
            $this->pageRepository->update($page->id, [
                'status' => 'draft',
                'published_at' => null,
                'unpublished_at' => now(),
                'unpublished_by' => $userId
            ]);

            // Save redirect if provided
            if ($redirectUrl) {
                $this->pageRepository->update($page->id, [
                    'unpublish_redirect_url' => $redirectUrl
                ]);
            }

            // Log history
            $this->historyService->logPageUnpublished($page->id, [
                'user_id' => $userId,
                'redirect_url' => $redirectUrl,
                'unpublished_at' => now()
            ]);
        });

        return $this->pageRepository->getCompletePageData($id);
    }

    public function makePageInternal(int $pageId, int $userId): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \Exception("Page not found");
        }

        if (!$page->canTransitionTo(PageStatus::INTERNAL)) {
            throw new \Exception("Cannot make page internal from current status");
        }

        return $this->database->transaction(function () use ($page, $userId) {
            $updatedPage = $this->pageRepository->update($page->id, [
                'status' => PageStatus::INTERNAL->value,
            ]);

            $this->historyService->logPageMadeInternal($page, $userId);

            return $this->getCompletePageData($updatedPage->id);
        });
    }

    public function updatePageSchedule(int $pageId, string $scheduledDate): Page
    {
        return $this->database->transaction(function () use ($pageId, $scheduledDate) {
            $page = $this->pageRepository->find($pageId);

            if (!$page) {
                throw new \Exception("Page not found");
            }

            $currentStatus = $page->status;

            $updateData = [
                'scheduled_at' => $scheduledDate,
                'published_at' => $scheduledDate,
                'status' => $currentStatus === PageStatus::PUBLISHED->value ? PageStatus::PUBLISHED->value : 'scheduled'
            ];

            $updatedPage = $this->pageRepository->update($pageId, $updateData);

            $this->historyService->logPageScheduleUpdated($pageId, $scheduledDate);

            return $this->getCompletePageData($updatedPage->id);
        });
    }

    public function approvePageWithMonetisationDecision(int $pageId, int $userId, array $decision): Page
    {
        $approvedPage = $this->database->transaction(function () use ($pageId, $userId, $decision) {
            $page = $this->pageRepository->find($pageId);

            if (!$page) {
                throw new \Exception("Page not found");
            }

            if (!$page->isWaitingApproval()) {
                throw new \Exception("Page is not waiting for approval");
            }

            $page->approve($userId);

            $updatedPage = $this->pageRepository->update($page->id, [
                'status' => PageStatus::PUBLISHED->value,
                'published_at' => date('Y-m-d H:i:s')
            ]);

            $this->historyService->logPageApproved($page, $userId);
            $this->historyService->logPagePublished($page->id);

            $freshPage = $this->getCompletePageData($updatedPage->id);

            $monetisationDecision = $decision['monetisation_decision'] ?? 'free';

            match ($monetisationDecision) {
                'premium' => $this->premiumApprovalService->approvePremium(
                    page: $freshPage,
                    editorId: $userId,
                    approvedPrice: (int)$decision['approved_price'],
                    note: $decision['premium_note'] ?? null,
                ),
                'reject_premium' => $this->premiumApprovalService->rejectPremium(
                    page: $freshPage,
                    editorId: $userId,
                    reason: (string)($decision['premium_rejection_reason'] ?? 'Premium request rejected by editor.'),
                ),
                default => $this->premiumApprovalService->approveFree(
                    page: $freshPage,
                    editorId: $userId,
                    note: $decision['premium_note'] ?? null,
                ),
            };

            return $this->getCompletePageData($updatedPage->id);
        });

        event(new ContentApproved(
            contentType: 'pages',
            contentId: (int)$approvedPage->id,
            siteId: (int)$approvedPage->site_id,
            actorId: $userId,
            title: (string)$approvedPage->title,
            ownerId: $this->pageOwnerId($approvedPage),
        ));

        return $approvedPage;
    }

    public function requestChangesForPage(int $pageId, int $adminId, string $notes): Page
    {
        $page = $this->findPage($pageId);

        if (!$page || $page->status !== PageStatus::WAITING_APPROVAL->value) {
            throw new \InvalidArgumentException("Page [{$pageId}] is not awaiting approval.");
        }

        $page->update([
            'status' => PageStatus::ON_HOLD->value,
            'moderation_notes' => $notes, // ASSUMED column exists or add via migration
        ]);

        return $page->refresh();
    }

    public function findPage(int $pageId): ?Page
    {
        return $this->pageRepository->find($pageId);
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
            'visibility' => fn($value) => $this->normaliseVisibility($value),
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

    private function formatDateTime(string|DateTime|null $dateString): ?string
    {
        if (!$dateString) {
            return null;
        }
        return is_string($dateString) ? (new DateTime($dateString))->format('Y-m-d H:i:s') : $dateString->format('Y-m-d H:i:s');
    }

    private function normaliseVisibility(mixed $value): string
    {
        $value = strtolower(trim((string)$value));

        if ($value === '') {
            return 'free';
        }

        $allowed = [
            'free',
            'member',
            'premium'
        ];

        return in_array($value, $allowed, true)
            ? $value
            : 'free';
    }

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

        $isCompletelyEmpty = count(array_filter($data, fn($v) => !empty($v))) === 0;

        if ($isCompletelyEmpty) {
            return;
        }

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
}
