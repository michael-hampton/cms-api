<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Models\Page;
use App\Repositories\PageRepository;
use App\Services\PageHistoryService;

class ClonePage
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly Database $database,
        private readonly PageHistoryService $historyService
    )
    {
    }

    /**
     * Duplicate a page with all its relations
     */
    public function handle(int $pageId): ?Page
    {
        $originalPage = $this->pageRepository->getCompletePageData($pageId);
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
                'gallery_slides' => $originalPage->gallery_slides, // ADD THIS
                'requires_approval' => $originalPage->requires_approval,
                'approved_by' => null,
                'approved_at' => null

            ];

            $newPage = $this->pageRepository->create($pageData);

            // Add clone history to both pages
            $originalPage->addCloneRecord('cloned_to', $newPage->id, null);
            $newPage->addCloneRecord('cloned_from', $originalPage->id, null);

            // Duplicate all relations using repository methods
            $this->duplicatePageRelations($originalPage->id, $newPage->id);

            $this->historyService->logPageDuplicated($pageId, $newPage->id);

            return $this->pageRepository->getCompletePageData($newPage->id);
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
            'products' => 'duplicateProducts',
        ];

        $errors = [];

        foreach ($relations as $relationType => $method) {
            try {
                $this->pageRepository->$method($sourcePageId, $targetPageId);
            } catch (\Exception $e) {
                if ($_ENV['APP_ENV'] !== 'testing') {
                    // Log the error but continue with other relations
                    error_log(sprintf(
                        'Failed to duplicate %s for page %d to %d: %s',
                        $relationType,
                        $sourcePageId,
                        $targetPageId,
                        $e->getMessage()
                    ));
                }

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