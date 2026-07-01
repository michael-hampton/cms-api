<?php

namespace App\Repositories\PublicContent;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Page;
use App\Models\PageTag;
use App\Models\Tag;
use App\Repositories\Repository;

final class PublicTagDirectoryRepository extends Repository
{
    public function findForSiteBySlug(int $siteId, string $slug): ?Tag
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
        return PageTag::with(['page.metadata', 'page.categories', 'page.tags', 'page.authors'])
            ->where('tag_id', $tagId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(static fn($item) => $item->page)
            ->filter(static fn($page): bool =>
                $page !== null
                && (int)$page->site_id === $siteId
                && (string)$page->status === 'published'
            );
    }

    public function baseIndexQuery(int $siteId): QueryBuilder
    {
        return Tag::where('site_id', $siteId);
    }

    public function basePagesQuery(int $siteId, int $tagId): QueryBuilder
    {
        return Page::where('pages.site_id', $siteId)
            ->where('pages.status', 'published')
            ->whereHas('tags', static fn(QueryBuilder $q) => $q->where('tags.id', $tagId))
            ->with(['metadata', 'categories', 'tags', 'authors']);
    }

    protected function getModelClass(): string
    {
        return Tag::class;
    }
}
