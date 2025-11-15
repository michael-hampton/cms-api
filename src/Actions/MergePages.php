<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Models\PageMetadata;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Repositories\BlockRepository;
use App\Repositories\PageCustomFieldRepository;
use App\Repositories\PageRepository;
use Exception;

class MergePages
{
    private int $siteId;

    public function __construct(
        private readonly PageRepository            $pageRepository,
        private readonly BlockRepository           $blockRepository,
        private readonly PageCustomFieldRepository $customFieldRepository,
        private readonly Database                  $database,
        ?int                                       $siteId = null
    )
    {
        $this->siteId = !empty($siteId) ? $siteId : SiteContext::getId();
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
    public function mergePages(int $sourcePageId, int $targetPageId, array $options = []): array
    {
        if ($sourcePageId === $targetPageId) {
            throw new \Exception("Cannot merge a page with itself");
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

        // Merge user-provided relations config with defaults
        $options['relations'] = array_merge($defaultRelations, $options['relations'] ?? []);

        $sourcePage = $this->pageRepository->getCompletePageData($sourcePageId);
        $targetPage = $this->pageRepository->getCompletePageData($targetPageId);

        if (!$sourcePage || !$targetPage) {
            throw new \Exception("Source or target page not found");
        }

        try {
            return $this->database->transaction(function () use ($sourcePage, $targetPage, $options) {

                // Merge relations based on strategy
                $relationResults = $this->mergePageRelations(
                    $sourcePage->id,
                    $targetPage->id,
                    $options['strategy'] ?? 'append',
                    $options['relations']
                );

                // Optionally merge main page data
                if (!empty($options['merge_content'])) {
                    $this->mergePageContent($sourcePage, $targetPage, $options);
                }

                // Add merge history to target page before deleting source
                $targetPage->addCloneRecord('merged_from', $sourcePage->id, null);

                // Add merge history to source page
                $sourcePage->addCloneRecord('merged_to', $targetPage->id, null);

                // Delete source page
                $this->pageRepository->delete($sourcePage->id);

                return [
                    'page' => $this->pageRepository->getCompletePageData($targetPage->id),
                    'results' => $relationResults,
                    'source_page_id' => $sourcePage->id,
                    'target_page_id' => $targetPage->id,
                ];
            });
        } catch (\Exception $e) {
            if ($_ENV['APP_ENV'] !== 'testing') {
                error_log(sprintf(
                    'Failed to merge page %d into %d: %s',
                    $sourcePageId,
                    $targetPageId,
                    $e->getMessage()
                ));
            }

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
    private function mergePageRelations(
        int    $sourcePageId,
        int    $targetPageId,
        string $strategy,
        array  $relations
    ): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => []
        ];

        // Many-to-many relations that can be appended
        $appendableRelations = [
            'categories' => 'duplicateCategories',
            'tags' => 'duplicateTags',
            'accessRoles' => 'duplicateAccessRoles',
            'regionSets' => 'duplicateRegionSets',
            'territories' => 'duplicateTerritories',
            'pageAuthors' => 'duplicatePageAuthors',
            'customFields' => 'duplicateCustomFields',
            'products' => 'duplicateProducts'
        ];

        foreach ($appendableRelations as $relation => $method) {
            // Check if this relation should be merged
            if (!($relations[$relation] ?? false)) {
                $results['skipped'][] = $relation;
                continue;
            }

            try {
                $this->pageRepository->$method($sourcePageId, $targetPageId);
                $results['success'][] = $relation;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'relation' => $relation,
                    'error' => $e->getMessage()
                ];

                if ($_ENV['APP_ENV'] !== 'testing') {
                    error_log(sprintf(
                        'Failed to merge %s from page %d to %d: %s',
                        $relation,
                        $sourcePageId,
                        $targetPageId,
                        $e->getMessage()
                    ));
                }
            }
        }

        // Blocks - always merge if enabled (structural content)
        if ($relations['blocks'] ?? false) {
            try {
                $this->mergeBlocks($sourcePageId, $targetPageId, $strategy);
                $results['success'][] = 'blocks';
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'relation' => 'blocks',
                    'error' => $e->getMessage()
                ];
            }
        } else {
            $results['skipped'][] = 'blocks';
        }

        // One-to-one relations - strategy dependent
        $oneToOneRelations = ['metadata', 'seo', 'settings', 'social'];

        if ($strategy === 'replace') {
            foreach ($oneToOneRelations as $relation) {
                if (!($relations[$relation] ?? false)) {
                    $results['skipped'][] = $relation;
                    continue;
                }

                try {
                    $this->replaceOneToOneRelation($sourcePageId, $targetPageId, $relation);
                    $results['success'][] = $relation;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'relation' => $relation,
                        'error' => $e->getMessage()
                    ];
                }
            }
        } elseif ($strategy === 'append') {
            // For settings/metadata, merge specific fields if enabled
            if ($relations['settings'] ?? false) {
                try {
                    $this->mergeSettings($sourcePageId, $targetPageId);
                    $results['success'][] = 'settings';
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'relation' => 'settings',
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $results['skipped'][] = 'settings';
            }
        }

        if ($relations['customFields'] ?? false) {
            $this->mergeCustomFields($sourcePageId, $targetPageId);
        }

        // Merge listing and hero data based on strategy
        try {
            $this->mergeListingAndHeroData($sourcePageId, $targetPageId, $strategy);
        } catch (\Exception $e) {
        }

        return $results;
    }

    private function replaceOneToOneRelation(int $sourcePageId, int $targetPageId, string $relation): void
    {
        $modelMap = [
            'metadata' => PageMetadata::class,
            'seo' => PageSeo::class,
            'settings' => PageSettings::class,
            'social' => PageSocial::class
        ];

        $modelClass = $modelMap[$relation] ?? null;
        if (!$modelClass) {
            throw new \Exception("Unknown relation: {$relation}");
        }

        // Delete existing
        $modelClass::where('page_id', $targetPageId)->delete();

        // Copy from source
        $method = 'duplicate' . ucfirst($relation);
        $this->pageRepository->$method($sourcePageId, $targetPageId);
    }

    /**
     * Merge listing and hero data from source to target based on strategy
     */
    private function mergeListingAndHeroData(int $sourcePageId, int $targetPageId, string $strategy): void
    {
        $sourcePage = $this->pageRepository->find($sourcePageId);
        $targetPage = $this->pageRepository->find($targetPageId);

        if (!$sourcePage || !$targetPage) {
            return;
        }

        $updates = match ($strategy) {
            'replace' => $this->buildReplaceUpdates($sourcePage),
            'append' => $this->buildAppendUpdates($sourcePage, $targetPage),
            default => []
        };

        if (!empty($updates)) {
            $this->pageRepository->update($targetPageId, $updates);
        }
    }

    /**
     * Build updates for replace strategy - copy all fields from source
     */
    private function buildReplaceUpdates(Page $sourcePage): array
    {
        $fields = [
            'listing_synopsis',
            'listing_title',
            'listing_label',
            'listing_image_id',
            'listing_use_as_hero',
            'hero_type',
            'hero_image_id',
            'hero_video_url',
            'crop_overrides',
            'resolved_images',
        ];

        return collect($fields)
            ->mapWithKeys(fn($field) => [$field => $sourcePage->$field])
            ->all();
    }

    /**
     * Build updates for append strategy - only fill empty fields
     */
    private function buildAppendUpdates(Page $sourcePage, Page $targetPage): array
    {
        $updates = [];

        // Simple scalar fields - only update if target is empty
        $scalarFields = [
            //'listing_synopsis',
            'listing_title',
            'listing_label',
            'listing_image_id',
            'hero_type',
            'hero_image_id',
            'hero_video_url',
        ];

        foreach ($scalarFields as $field) {
            if (in_array($targetPage->$field, ['', null, 0]) && $sourcePage->$field !== '') {
                $updates[$field] = $sourcePage->$field;
            }
        }

        // JSON fields - merge intelligently
        $updates = array_merge($updates, $this->mergeJsonFields($sourcePage, $targetPage));

        return $updates;
    }

    /**
     * Merge JSON fields intelligently, combining arrays
     */
    private function mergeJsonFields(Page $sourcePage, Page $targetPage): array
    {
        $updates = [];

        // Crop overrides - target takes precedence over source
        if (is_array($sourcePage->crop_overrides)) {
            $sourceOverrides = [...$sourcePage->crop_overrides] ?? [];
            $targetOverrides = [...$targetPage->crop_overrides] ?? [];
            $merged = array_merge($sourceOverrides, $targetOverrides);

            if (!empty($merged)) {
                $updates['crop_overrides'] = json_encode($merged);
            }
        }

        // Resolved images - target takes precedence over source
        if (is_array($sourcePage->resolved_images)) {
            $sourceImages = [...$sourcePage->resolved_images] ?? [];
            $targetImages = [...$targetPage->resolved_images] ?? [];
            $merged = array_merge($sourceImages, $targetImages);

            if (!empty($merged)) {
                $updates['resolved_images'] = json_encode($merged);
            }
        }

        return $updates;
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
}