<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Repositories\TagRepository;

class CloneTag
{
    public function __construct(
        private readonly Database       $database,
        private readonly TagRepository  $repository,
    )
    {
    }

    public function handle(int $tagId, ?string $newName = null, ?int $siteId = null): array
    {
        return $this->database->transaction(function() use ($tagId, $newName, $siteId) {
            $originalTag = $this->repository->find($tagId);

            if (!$originalTag) {
                throw new \Exception("Tag not found");
            }

            $results = ['success' => [], 'failed' => []];
            $targetSiteId = $siteId ?? SiteContext::getId();

            $data = [
                'name' => $newName ?? ($originalTag->name . ' (Copy)'),
                'description' => $originalTag->description,
                'status' => 'inactive',
                'seo_title' => $originalTag->seo_title,
                'seo_description' => $originalTag->seo_description,
                'no_index' => $originalTag->no_index ?? false,
                'canonical_url' => null, // Don't copy canonical URL
                'site_id' => $siteId ?? SiteContext::getId(),
            ];

            $baseName = $data['name'];
            $slug = Str::slug($baseName);
            $counter = 1;

            while ($this->repository->findBySlug($slug)) {
                $slug = Str::slug($baseName . '-' . $counter);
                $counter++;
            }

            $data['slug'] = $slug;

            $newTag = $this->repository->create($data);
            $results['success'][] = 'tag_created';

            // Add clone history with site information
            if ($targetSiteId !== $originalTag->site_id) {
                $originalTag->addCloneRecord('cloned_to', $newTag->id, $targetSiteId);
                $newTag->addCloneRecord('cloned_from', $originalTag->id, $originalTag->site_id);
                $results['success'][] = 'cross_site_clone_history';
            } else {
                $originalTag->addCloneRecord('cloned_to', $newTag->id, null);
                $newTag->addCloneRecord('cloned_from', $originalTag->id, null);
                $results['success'][] = 'clone_history';
            }

            return [
                'tag' => $newTag,
                'results' => $results,
                'original_tag_id' => $tagId,
                'cross_site' => $targetSiteId !== $originalTag->site_id
            ];
        });
    }
}