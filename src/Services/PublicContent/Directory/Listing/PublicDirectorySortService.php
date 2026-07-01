<?php
declare(strict_types=1);

namespace App\Services\PublicContent\Directory\Listing;

use App\Enums\PublicContent\PublicDirectoryIndexSort;
use App\Enums\PublicContent\PublicDirectoryPageSort;
use App\Framework\Database\QueryBuilder;

final class PublicDirectorySortService
{
    public function applyIndexSort(QueryBuilder $query, PublicDirectoryIndexSort $sort): QueryBuilder
    {
        return match ($sort) {
            PublicDirectoryIndexSort::NameAsc => $query->orderBy('name', 'asc'),
            PublicDirectoryIndexSort::NameDesc => $query->orderBy('name', 'desc'),
            PublicDirectoryIndexSort::Newest => $query->orderBy('created_at', 'desc'),
            PublicDirectoryIndexSort::Oldest => $query->orderBy('created_at', 'asc'),
            PublicDirectoryIndexSort::MostArticles => $query
                ->withCount('pages', static fn(QueryBuilder $q) => $q->where('status', 'published'))
                ->orderByRaw('pages_count desc'),
        };
    }

    public function applyPageSort(QueryBuilder $query, PublicDirectoryPageSort $sort): QueryBuilder
    {
        return match ($sort) {
            PublicDirectoryPageSort::Newest => $query->orderBy('published_at', 'desc'),
            PublicDirectoryPageSort::Oldest => $query->orderBy('published_at', 'asc'),
            PublicDirectoryPageSort::TitleAsc => $query->orderBy('title', 'asc'),
            PublicDirectoryPageSort::TitleDesc => $query->orderBy('title', 'desc'),
            PublicDirectoryPageSort::MostViewed => $query
                ->withCount('views')
                ->orderByRaw('views_count desc'),
            PublicDirectoryPageSort::MostCommented => $query
                ->withCount('comments', static fn(QueryBuilder $q) => $q->where('status', 'approved'))
                ->orderByRaw('comments_count desc'),
        };
    }
}