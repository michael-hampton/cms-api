<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\PageAuthor;

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

    public function getPageAuthors(int $pageId, ?string $role = null): Collection
    {
        $query = PageAuthor::with(['author'])
            ->where('page_id', $pageId);

        if ($role) {
            $query->where('role', $role);
        }

        return $query->ordered()->get();
    }

    public function duplicatePageAuthors(int $sourcePageId, int $targetPageId): void
    {
        $authors = PageAuthor::where('page_id', $sourcePageId)->get();

        foreach ($authors as $author) {
            PageAuthor::create([
                'page_id' => $targetPageId,
                'author_id' => $author->author_id,
                'role' => $author->role,
                'sort_order' => $author->sort_order
            ]);
        }
    }
}