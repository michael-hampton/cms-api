<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Repositories\TagRepository;

class TagService
{
    private Database $database;
    protected TagRepository $repository;


    public function __construct(Database $database, TagRepository $repository)
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
                    $page->tag_id = $reassignToTagId;
                    $page->save();
                }
                $tag->delete();
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

    public function duplicateTag(int $tagId, ?string $newName = null): bool
    {
        return $this->database->transaction(function() use ($tagId, $newName) {
            $originalTag = $this->repository->find($tagId);

            if (!$originalTag) {
                throw new \Exception("Tag not found");
            }

            $data = [
                'name' => $newName ?? ($originalTag->name . ' (Copy)'),
                'description' => $originalTag->description,
                'status' => 'inactive',
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

            return $newTag !== null;
        });
    }
}