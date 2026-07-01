<?php
declare(strict_types=1);

namespace App\Services\PublicContent\Directory\Listing;

use App\Data\PublicContent\Listing\ListingFilterData;
use App\Enums\PublicContent\PublicDirectoryFacet;
use App\Framework\Database\QueryBuilder;

class PublicDirectoryFilterBuilder
{
    public function forIndex(QueryBuilder $query, ListingFilterData $filter): QueryBuilder
    {
        if ($this->hasSearch($filter)) {
            $query->whereLike('name', "%$filter->search%");
        }

        return $query;
    }

    public function forPages(QueryBuilder $query, ListingFilterData $filter): QueryBuilder
    {
        if ($this->hasSearch($filter)) {
            $search = "%$filter->search%";
            $query->where(function (QueryBuilder $inner) use ($search) {
                $inner->whereLike('title', $search)
                    ->orWhereLike('meta_description', $search);
            });
        }

        foreach ($filter->facets as $facetKey => $values) {
            $facet = PublicDirectoryFacet::tryFrom($facetKey);

            if ($facet === null || empty($values)) {
                continue;
            }

            $this->applyFacet($query, $facet, $values);
        }

        return $query;
    }

    private function hasSearch(ListingFilterData $filter): bool
    {
        return $filter->search !== null && trim($filter->search) !== '';
    }

    /**
     * @param list<string> $values
     */
    private function applyFacet(QueryBuilder $query, PublicDirectoryFacet $facet, array $values): void
    {
        match ($facet) {
            PublicDirectoryFacet::Category => $query->whereHas(
                'categories',
                static fn(QueryBuilder $q) => $q->whereIn('categories.id', $values),
            ),
            PublicDirectoryFacet::Tag => $query->whereHas(
                'tags',
                static fn(QueryBuilder $q) => $q->whereIn('tags.id', $values),
            ),
            PublicDirectoryFacet::Author => $query->whereHas(
                'authors',
                static fn(QueryBuilder $q) => $q->whereIn('authors.id', $values),
            ),
            PublicDirectoryFacet::Year => $query->where(function (QueryBuilder $inner) use ($values) {
                foreach ($values as $index => $year) {
                    $index === 0
                        ? $inner->whereYear('published_at', '=', (int) $year)
                        : $inner->orWhereRaw('YEAR(published_at) = ?', [(int) $year]);
                }
            }),
            PublicDirectoryFacet::Month => $query->where(function (QueryBuilder $inner) use ($values) {
                foreach ($values as $index => $month) {
                    $index === 0
                        ? $inner->whereMonth('published_at', '=', (int) $month)
                        : $inner->orWhereRaw('MONTH(published_at) = ?', [(int) $month]);
                }
            }),
            PublicDirectoryFacet::ArticleType => $query->whereIn('page_type', $values),
        };
    }
}