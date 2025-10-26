<?php

namespace App\Repositories;

use App\Models\PageTag;
use App\Models\Tag;

class PageTagRepository extends Repository
{
    private $tagRepository;

    public function __construct()
    {
        parent::__construct();
        $this->withoutSiteFilter();
        $this->tagRepository = new TagRepository();
    }

    protected function getModelClass(): string
    {
        return PageTag::class;
    }

    public function syncTags(int $pageId, array $tagNames, int $siteId): void
    {
        // Get existing page tags to update usage counts
        $existingTags = $this->getPageTags($pageId, $siteId);

        // Delete existing page tags
        $this->database->delete('page_tags', ['page_id' => $pageId]);

        $formattedExistingTags = [];

        // Decrement usage count for old tags
        foreach ($existingTags as $tag) {
            $tag->decrementUsage();
            $formattedExistingTags[$tag->name] = $tag;
        }

        // Process new tags
        foreach ($tagNames as $tagName) {
            if (!empty(trim($tagName))) {
                // Find or create tag (this will increment usage count)
                $tag = !empty($formattedExistingTags[$tagName]) ?
                    $formattedExistingTags[$tagName] :
                    $this->tagRepository->findOrCreateByName(trim($tagName), $siteId);

                // Create page-tag relationship
                $this->create([
                    'page_id' => $pageId,
                    'tag_id' => $tag->id
                ]);
            }
        }
    }

    public function getPageTags(int $pageId, int $siteId): array
    {
        $results = $this->database->select(
            "SELECT t.* FROM tags t 
             LEFT JOIN page_tags pt ON t.id = pt.tag_id AND pt.page_id = ?
            WHERE t.site_id = ?
             ORDER BY t.name ASC",
            [$pageId, $siteId]
        );



        $models = [];
        foreach ($results as $data) {
            $model = new Tag($data);
            $model->exists = true;
            $model->original = $model->attributes;
            $models[] = $model;
        }

        return $models;
    }
}