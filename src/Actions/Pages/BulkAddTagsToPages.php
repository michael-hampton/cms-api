<?php

namespace App\Actions\Pages;

use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\PageTagRepository;
use App\Repositories\Cms\TagRepository;

class BulkAddTagsToPages
{
    public function __construct(
        private readonly PageRepository    $pageRepository,
        private readonly PageTagRepository $pageTagRepository,
        private readonly TagRepository     $tagRepository
    )
    {
    }

    public function handle(array $pageIds, array $tagIds, int $siteId): array
    {
        $results = [];

        // Get tag names from tag IDs once at the start
        $tagNames = [];
        foreach ($tagIds as $tagId) {
            $tag = $this->tagRepository->find($tagId);
            if ($tag) {
                $tagNames[] = $tag->name;
            }
        }

        // Early return if no valid tags
        if (empty($tagNames)) {
            foreach ($pageIds as $pageId) {
                $results[$pageId] = [
                    'success' => false,
                    'error' => 'No valid tags provided'
                ];
            }
            return $results;
        }

        foreach ($pageIds as $pageId) {
            try {
                $page = $this->pageRepository->find($pageId);

                if (!$page) {
                    $results[$pageId] = [
                        'success' => false,
                        'error' => 'Page not found'
                    ];
                    continue;
                }

                // Get existing tag names for this page using getPageTags
                $existingTags = $this->pageTagRepository->getPageTags($pageId, $siteId);
                $existingTagNames = array_map(function ($tag) {
                    return $tag->name;
                }, $existingTags);

                // Merge existing with new tag names (avoiding duplicates)
                $allTagNames = array_unique(array_merge($existingTagNames, $tagNames));

                // Sync tags with tag names
                $this->pageTagRepository->syncTags($pageId, $allTagNames, $siteId);

                $results[$pageId] = ['success' => true];
            } catch (\Exception $e) {
                $results[$pageId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}