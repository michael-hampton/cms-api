<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Model;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;

class TagService
{
    private Database $database;
    protected TagRepository $repository;


    public function __construct(
        Database $database,
        TagRepository $repository,
        private PageRepository $pageRepository,
    )
    {
        $this->database = $database ?? Database::getInstance();
        $this->repository = $repository;
    }

    public function delete(int $tagId, ?int $reassignToTagId = null): bool
    {
        $tag = $this->repository->find($tagId);

        if (!$tag) {
            throw new \Exception('Tag not found');
        }

        $pagesCount = $this->repository->getPagesByTagId($tagId)->count();

        if ($pagesCount > 0) {
            if ($reassignToTagId === null) {
                throw new CannotDeleteException('tag', $pagesCount);
            }

            if ($reassignToTagId === $tagId) {
                throw new \InvalidArgumentException('Cannot reassign to the same tag being deleted');
            }

            $reassignTag = $this->repository->find($reassignToTagId);

            if (!$reassignTag) {
                throw new \Exception('Reassignment tag not found');
            }

            $this->database->transaction(function () use ($tagId, $tag, $reassignToTagId) {
                // Get pages and update them individually
                $pages = $this->repository->getPagesByTagId($tagId);
                foreach ($pages as $page) {
                    $this->pageRepository->syncTags($page->id, $reassignToTagId);
                }
            });

            return true;
        }

        return $tag->delete();
    }

    public function checkDeletable(int $tagId): array
    {
        $tag = $this->repository->find($tagId);

        if (!$tag) {
            throw new \Exception('Tag not found');
        }

        $pagesCount = $tag->pages()->count();

        return [
            'can_delete' => $pagesCount === 0,
            'pages_count' => $pagesCount,
            'requires_reassignment' => $pagesCount > 0
        ];
    }

    public function getAlternativeTags(int $tagId): Collection
    {
        return $this->repository->getAlternatives($tagId);
    }

    public function duplicateTag(int $tagId, ?string $newName = null, ?int $siteId = null): Model
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

    public function mergeTags(int $fromTagId, int $toTagId): bool
    {
        if ($fromTagId === $toTagId) {
            throw new \InvalidArgumentException('Cannot merge a tag with itself');
        }

        $fromTag = $this->repository->find($fromTagId);
        if (!$fromTag) {
            throw new \Exception('Source tag not found');
        }

        $toTag = $this->repository->find($toTagId);
        if (!$toTag) {
            throw new \Exception('Target tag not found');
        }

        // Add merge history before merging
        $toTag->addCloneRecord('merged_from', $fromTag->id, null);
        $fromTag->addCloneRecord('merged_to', $toTag->id, null);

        return $this->repository->mergeTags($fromTagId, $toTagId);
    }

    public function bulkDelete(array $tagIds): array
    {
        return $this->database->transaction(function() use ($tagIds) {
            $deleted = [];
            $failed = [];

            foreach ($tagIds as $tagId) {
                try {
                    $tag = $this->repository->find($tagId);

                    if (!$tag) {
                        $failed[] = ['id' => $tagId, 'reason' => 'Tag not found'];
                        continue;
                    }

                    $pagesCount = $this->repository->getPagesByTagId($tagId)->count();

                    if ($pagesCount > 0) {
                        $failed[] = [
                            'id' => $tagId,
                            'reason' => "Tag has {$pagesCount} associated pages"
                        ];
                        continue;
                    }

                    if ($tag->delete()) {
                        $deleted[] = $tagId;
                    } else {
                        $failed[] = ['id' => $tagId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $tagId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($tagIds)
            ];
        });
    }
}