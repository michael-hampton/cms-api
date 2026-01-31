<?php

namespace App\Actions\Pages;

use App\Framework\Database\Database;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\ClonePermissionChecker;
use App\Services\Cms\Pages\PageHistoryService;

class ClonePage
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly Database $database,
        private readonly PageHistoryService     $historyService,
        private readonly ClonePermissionChecker $permissionChecker
    )
    {
    }

    /**
     * Duplicate a page with all its relations
     */
    public function handle(int $pageId, array $options = [], ?int $userId = null): ?array
    {
        $originalPage = $this->pageRepository->getCompletePageData($pageId);
        if (!$originalPage) {
            return null;
        }

        // Check clone permissions
        if ($userId !== null && !$this->permissionChecker->canClone($originalPage, $userId)) {
            $reason = $this->permissionChecker->getCloneRestrictionReason($originalPage, $userId);
            throw new \Exception($reason ?? 'Page cannot be cloned');
        }

        // Set default relations - all enabled by default
        $defaultRelations = [
            'categories' => true,
            'tags' => true,
            'accessRoles' => true,
            'regionSets' => true,
            'territories' => true,
            'pageAuthors' => true,
            'customFields' => true,
            'products' => true,
            'blocks' => true,
            'metadata' => true,
            'seo' => true,
            'settings' => true,
            'social' => true,
            'owner' => true
        ];

        $options['relations'] = array_merge($defaultRelations, $options['relations'] ?? []);

        return $this->database->transaction(function () use ($originalPage, $pageId, $options) {
            $pageData = [
                'title' => $originalPage->title . ' (Copy)',
                'slug' => $originalPage->slug . '-copy-' . time(),
                'status' => 'draft',
                'meta_title' => $originalPage->meta_title,
                'meta_description' => $originalPage->meta_description,
                'site_id' => $originalPage->site_id,
                'listing_synopsis' => $originalPage->listing_synopsis,
                'listing_title' => $originalPage->listing_title,
                'listing_label' => $originalPage->listing_label,
                'listing_image_id' => $originalPage->listing_image_id,
                'listing_use_as_hero' => $originalPage->listing_use_as_hero,
                'hero_type' => $originalPage->hero_type,
                'hero_image_id' => $originalPage->hero_image_id,
                'hero_video_url' => $originalPage->hero_video_url,
                'crop_overrides' => $originalPage->crop_overrides,
                'resolved_images' => $originalPage->resolved_images,
                'page_type' => $originalPage->page_type,
                'gallery_slides' => $originalPage->gallery_slides,
                'requires_approval' => $originalPage->requires_approval,
                'approved_by' => null,
                'approved_at' => null
            ];

            $newPage = $this->pageRepository->create($pageData);

            // Add clone history to both pages
            $originalPage->addCloneRecord('cloned_to', $newPage->id, null);
            $newPage->addCloneRecord('cloned_from', $originalPage->id, null);

            // Duplicate relations using repository methods
            $relationResults = $this->duplicatePageRelations(
                $originalPage->id,
                $newPage->id,
                $options['relations']
            );

            $this->historyService->logPageDuplicated($pageId, $newPage->id);

            return [
                'page' => $this->pageRepository->getCompletePageData($newPage->id),
                'results' => $relationResults,
                'original_page_id' => $pageId,
            ];
        });
    }

    /**
     * Duplicate all relations from source to target page
     * This method orchestrates the duplication of all page relations
     */
    private function duplicatePageRelations(int $sourcePageId, int $targetPageId, array $relations): array
    {
        $relationMethods = [
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
            'products' => 'duplicateProducts',
            'owner' => 'duplicateOwner',
        ];

        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => []
        ];

        foreach ($relationMethods as $relationType => $method) {
            // Check if this relation should be duplicated
            if (!($relations[$relationType] ?? false)) {
                $results['skipped'][] = $relationType;
                continue;
            }

            try {
                $this->pageRepository->$method($sourcePageId, $targetPageId);
                $results['success'][] = $relationType;
            } catch (\Exception $e) {
                if ($_ENV['APP_ENV'] !== 'testing') {
                    error_log(sprintf(
                        'Failed to duplicate %s for page %d to %d: %s',
                        $relationType,
                        $sourcePageId,
                        $targetPageId,
                        $e->getMessage()
                    ));
                }

                $results['failed'][] = [
                    'relation' => $relationType,
                    'error' => $e->getMessage()
                ];
            }
        }

        // If critical relations failed, you might want to throw
        if (!empty($results['failed']) && count($results['failed']) === count($relationMethods)) {
            throw new \Exception('Failed to duplicate any page relations: ' . json_encode($results['failed']));
        }

        return $results;
    }
}