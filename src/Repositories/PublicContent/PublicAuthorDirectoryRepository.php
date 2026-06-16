<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Author;
use App\Models\PageAuthor;
use App\Repositories\Repository;

final class PublicAuthorDirectoryRepository extends Repository
{
    public function findActiveBySlug(int $siteId, string $slug): ?Author
    {
        $author = Author::where('site_id', $siteId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        return $author instanceof Author ? $author : null;
    }

    public function getActive(int $siteId): Collection
    {
        return Author::where('site_id', $siteId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getPublishedPages(int $siteId, int $authorId): Collection
    {
        return PageAuthor::with(['page.metadata', 'page.categories', 'page.tags', 'page.authors'])
            ->where('author_id', $authorId)
            ->orderBy('sort_order')
            ->get()
            ->map(static fn($item) => $item->page)
            ->filter(static fn($page): bool =>
                $page !== null
                && (int)$page->site_id === $siteId
                && (string)$page->status === 'published'
            );
    }

    protected function getModelClass(): string
    {
        return Author::class;
    }
}
