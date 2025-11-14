<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Model;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;

class CloneTag
{
    public function __construct(
        private readonly Database       $database,
        private readonly TagRepository  $repository,
        private readonly PageRepository $pageRepository,
    )
    {
    }

    public function handle(int $tagId, ?string $newName = null, ?int $siteId = null): Model
    {
        return $this->database->transaction(function() use ($tagId, $newName, $siteId) {
            $originalTag = $this->repository->find($tagId);

            if (!$originalTag) {
                throw new \Exception("Tag not found");
            }

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

            // Add clone history with site information
            if ($targetSiteId !== $originalTag->site_id) {
                $originalTag->addCloneRecord('cloned_to', $newTag->id, $targetSiteId);
                $newTag->addCloneRecord('cloned_from', $originalTag->id, $originalTag->site_id);
            } else {
                $originalTag->addCloneRecord('cloned_to', $newTag->id, null);
                $newTag->addCloneRecord('cloned_from', $originalTag->id, null);
            }

            return $newTag;
        });
    }
}