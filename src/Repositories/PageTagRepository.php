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
        $existingTags = $this->getPageTags($pageId);

        // Delete existing page tags
        $this->database->delete('page_tags', ['page_id' => $pageId]);

        // Decrement usage count for old tags
        foreach ($existingTags as $tag) {
            $tag->decrementUsage();
        }

        // Process new tags
        foreach ($tagNames as $tagName) {
            if (!empty(trim($tagName))) {
                // Find or create tag (this will increment usage count)
                $tag = $this->tagRepository->findOrCreateByName(trim($tagName), $siteId);

                // Create page-tag relationship
                $this->create([
                    'page_id' => $pageId,
                    'tag_id' => $tag->id
                ]);
            }
        }
    }

    public function syncTagsByIds(int $pageId, array $tagIds): void
    {
        // Get existing page tags to update usage counts
        $existingTagIds = $this->getPageTagIds($pageId);

        // Delete existing page tags
        $this->database->delete('page_tags', ['page_id' => $pageId]);

        // Decrement usage count for removed tags
        foreach ($existingTagIds as $tagId) {
            if (!in_array($tagId, $tagIds)) {
                $tag = $this->tagRepository->find($tagId);
                if ($tag) {
                    $tag->decrementUsage();
                }
            }
        }

        // Create new relationships and increment usage for new tags
        foreach ($tagIds as $tagId) {
            if (is_numeric($tagId)) {
                $this->create([
                    'page_id' => $pageId,
                    'tag_id' => (int) $tagId
                ]);

                // Increment usage if this is a new tag for this page
                if (!in_array($tagId, $existingTagIds)) {
                    $tag = $this->tagRepository->find($tagId);
                    if ($tag) {
                        $tag->incrementUsage();
                    }
                }
            }
        }
    }

    public function getPageTags(int $pageId): array
    {
        $results = $this->database->select(
            "SELECT t.* FROM tags t 
             INNER JOIN page_tags pt ON t.id = pt.tag_id 
             WHERE pt.page_id = ? 
             ORDER BY t.name ASC",
            [$pageId]
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

    public function getPageTagIds(int $pageId): array
    {
        $results = $this->database->select(
            "SELECT tag_id FROM page_tags WHERE page_id = ?",
            [$pageId]
        );

        return array_column($results, 'tag_id');
    }

    public function getPagesByTag(int $tagId, string $status = 'published'): array
    {
        $results = $this->database->select(
            "SELECT p.* FROM pages p 
             INNER JOIN page_tags pt ON p.id = pt.page_id 
             WHERE pt.tag_id = ? AND p.status = ? 
             ORDER BY p.created_at DESC",
            [$tagId, $status]
        );

        $models = [];
        foreach ($results as $data) {
            $model = new \App\Models\Page($data);
            $model->exists = true;
            $model->original = $model->attributes;
            $models[] = $model;
        }

        return $models;
    }

    public function getTagStats(int $tagId): array
    {
        $results = $this->database->select(
            "SELECT 
                COUNT(*) as total_pages,
                COUNT(CASE WHEN p.status = 'published' THEN 1 END) as published_pages,
                COUNT(CASE WHEN p.status = 'draft' THEN 1 END) as draft_pages
             FROM page_tags pt
             LEFT JOIN pages p ON pt.page_id = p.id
             WHERE pt.tag_id = ?",
            [$tagId]
        );

        return $results[0] ?? [
            'total_pages' => 0,
            'published_pages' => 0,
            'draft_pages' => 0
        ];
    }
}