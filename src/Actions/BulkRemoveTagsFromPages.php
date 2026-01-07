<?php

namespace App\Actions;

use App\Repositories\PageRepository;
use App\Repositories\PageTagRepository;
use App\Repositories\TagRepository;

class BulkRemoveTagsFromPages
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
        $tagNamesToRemove = [];
        foreach ($tagIds as $tagId) {
            $tag = $this->tagRepository->find($tagId);
            if ($tag) {
                $tagNamesToRemove[] = $tag->name;
            }
        }

        // Early return if no valid tags
        if (empty($tagNamesToRemove)) {
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

                // Get existing tag names
                $existingTagNames = $this->pageTagRepository
                    ->getTagsForPage($pageId)
                    ->pluck('name')
                    ->toArray();

                // Remove specified tag names
                $remainingTagNames = array_diff($existingTagNames, $tagNamesToRemove);

                // Sync remaining tags
                $this->pageTagRepository->syncTags($pageId, array_values($remainingTagNames), $siteId);

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