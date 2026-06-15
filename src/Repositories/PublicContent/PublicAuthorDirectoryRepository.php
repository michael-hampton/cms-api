<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Author;
use App\Models\Page;
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
        return Page::with(['metadata', 'categories', 'tags', 'authors'])
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->whereHas('authors', static fn($query) => $query->where('authors.id', $authorId))
            ->orderByDesc('published_at')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Author::class;
    }
}
