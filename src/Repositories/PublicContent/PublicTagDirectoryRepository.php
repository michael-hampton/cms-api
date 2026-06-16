<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
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

    protected function getModelClass(): string
    {
        return Tag::class;
    }
}
