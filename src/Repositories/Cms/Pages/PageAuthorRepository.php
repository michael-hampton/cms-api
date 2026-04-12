<?php

namespace App\Repositories\Cms\Pages;

use App\Models\PageAuthor;
use App\Repositories\Repository;

class PageAuthorRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PageAuthor::class;
    }

    public function syncAuthors(int $pageId, array $authorIds, string $role, int $siteId): void
    {
        // Delete existing authors for this page and role
        PageAuthor::where('page_id', $pageId)
            ->where('role', $role)
            ->delete();

        // Insert new authors
        foreach ($authorIds as $index => $authorId) {
            PageAuthor::create([
                'page_id' => $pageId,
                'author_id' => $authorId,
                'role' => $role,
                'sort_order' => $index
            ]);
        }
    }

    public function getAuthorsForPage(int $pageId, string $role): array
    {
        return PageAuthor::where('page_id', $pageId)
            ->where('role', $role)
            ->orderBy('sort_order')
            ->get()
            ->pluck('author_id')
            ->toArray();
    }

    public function isLinked(int $pageId, int $authorId): bool
    {
        return PageAuthor::where('page_id', $pageId)
            ->where('author_id', $authorId)
            ->exists();
    }

    public function link(int $pageId, int $authorId): void
    {
        if (!$this->isLinked($pageId, $authorId)) {
            $this->create([
                'page_id' => $pageId,
                'author_id' => $authorId,
            ]);
        }
    }

    public function unlink(int $pageId, int $authorId): void
    {
        PageAuthor::where('page_id', $pageId)
            ->where('author_id', $authorId)
            ->delete();
    }
}