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
}