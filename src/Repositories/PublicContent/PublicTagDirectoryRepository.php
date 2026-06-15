<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Page;
use App\Models\Tag;
use App\Repositories\Repository;

final class PublicTagDirectoryRepository extends Repository
{
    public function findBySlug(int $siteId, string $slug): ?Tag
    {
        $tag = Tag::where('site_id', $siteId)
            ->where('slug', $slug)
            ->first();

        return $tag instanceof Tag ? $tag : null;
    }

    public function getAll(int $siteId): Collection
    {
        return Tag::where('site_id', $siteId)
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->get();
    }

    public function getPublishedPages(int $siteId, int $tagId): Collection
    {
        return Page::with(['metadata', 'categories', 'tags', 'authors'])
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->whereHas('tags', static fn($query) => $query->where('tags.id', $tagId))
            ->orderByDesc('published_at')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Tag::class;
    }
}
