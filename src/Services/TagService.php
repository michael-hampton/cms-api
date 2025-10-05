<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
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

        $pagesCount = $tag->pages()->count();

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

            $this->database->transaction(function () use ($tag, $reassignToTagId) {
                $pages = $tag->pages()->get();

                foreach ($pages as $page) {
                    $page->tags()->detach($tag->id);
                    if (!$page->tags()->where('tag_id', $reassignToTagId)->exists()) {
                        $page->tags()->attach($reassignToTagId);
                    }
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
}