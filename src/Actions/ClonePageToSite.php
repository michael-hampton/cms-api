<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Models\Page;
use App\Repositories\PageRepository;
use App\Services\PageHistoryService;
use Exception;

class ClonePageToSite
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly Database $database,
        private readonly PageHistoryService $historyService
    )
    {
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
    public function handle(int $pageId, int $targetSiteId, ?string $newTitle = null): Page
    {
        $sourcePage = $this->pageRepository->getCompletePageData($pageId);
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
                'site_id' => $targetSiteId,
                'listing_synopsis' => $sourcePage->listing_synopsis,
                'listing_title' => $sourcePage->listing_title,
                'listing_dek_label' => $sourcePage->listing_dek_label,
                'listing_image_id' => $sourcePage->listing_image_id,
                'listing_use_as_hero' => $sourcePage->listing_use_as_hero,
                'hero_type' => $sourcePage->hero_type,
                'hero_image_id' => $sourcePage->hero_image_id,
                'hero_video_url' => $sourcePage->hero_video_url,
                'crop_overrides' => $sourcePage->crop_overrides,
                'resolved_images' => $sourcePage->resolved_images,
                'page_type' => $sourcePage->page_type,
                'gallery_slides' => $sourcePage->gallery_slides,
                'requires_approval' => $sourcePage->requires_approval,
                'approved_by' => null,
                'approved_at' => null
            ];

            $newPage = $this->pageRepository->create($pageData);

            // Add clone history with site information
            $sourcePage->addCloneRecord('cloned_to', $newPage->id, $targetSiteId);
            $newPage->addCloneRecord('cloned_from', $sourcePage->id, $sourcePage->site_id);

            // Clone all relations to the new site
            $this->clonePageRelationsToSite($sourcePage->id, $newPage->id, $targetSiteId);

            $this->historyService->logPageClonedToSite($sourcePage->id, $newPage->id, $targetSiteId);

            return $this->pageRepository->getCompletePageData($newPage->id);
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
            'products' => 'duplicateProductsToSite',
        ];

        $errors = [];

        foreach ($relations as $relationType => $method) {
            try {
                $this->pageRepository->$method($sourcePageId, $targetPageId, $targetSiteId);
            } catch (\Exception $e) {
                if ($_ENV['APP_ENV'] !== 'testing') {
                    // Log the error but continue with other relations
                    error_log(sprintf(
                        'Failed to clone %s for page %d to site %d: %s',
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