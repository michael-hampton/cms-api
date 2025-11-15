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
    public function handle(int $pageId, int $targetSiteId, ?string $newTitle = null, array $options = []): array
    {
        $sourcePage = $this->pageRepository->getCompletePageData($pageId);
        if (!$sourcePage) {
            throw new \Exception("Source page not found");
        }

        if ($sourcePage->site_id === $targetSiteId) {
            throw new \Exception("Source and target site cannot be the same");
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
        ];

        $options['relations'] = array_merge($defaultRelations, $options['relations'] ?? []);

        return $this->database->transaction(function () use ($sourcePage, $targetSiteId, $newTitle, $options) {
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
            $relationResults = $this->clonePageRelationsToSite(
                $sourcePage->id,
                $newPage->id,
                $targetSiteId,
                $options['relations']
            );

            $this->historyService->logPageClonedToSite($sourcePage->id, $newPage->id, $targetSiteId);

            return [
                'page' => $this->pageRepository->getCompletePageData($newPage->id),
                'results' => $relationResults,
                'original_page_id' => $sourcePage->id,
                'original_site_id' => $sourcePage->site_id,
                'target_site_id' => $targetSiteId,
            ];
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
    private function clonePageRelationsToSite(
        int   $sourcePageId,
        int   $targetPageId,
        int   $targetSiteId,
        array $relations
    ): array
    {
        $relationMethods = [
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

        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => []
        ];

        foreach ($relationMethods as $relationType => $method) {
            // Check if this relation should be cloned
            if (!($relations[$relationType] ?? false)) {
                $results['skipped'][] = $relationType;
                continue;
            }

            try {
                $this->pageRepository->$method($sourcePageId, $targetPageId, $targetSiteId);
                $results['success'][] = $relationType;
            } catch (\Exception $e) {
                if ($_ENV['APP_ENV'] !== 'testing') {
                    error_log(sprintf(
                        'Failed to clone %s for page %d to site %d: %s',
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