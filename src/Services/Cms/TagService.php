<?php

namespace App\Services\Cms;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;

class TagService
{

    public function __construct(
        private readonly Database       $database,
        private readonly TagRepository  $repository,
        private readonly PageRepository $pageRepository,
    )
    {
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
}