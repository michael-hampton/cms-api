<?php
declare(strict_types=1);

namespace App\Services\PublicContent\Directory\Listing;

use App\Data\PublicContent\Listing\FacetGroupData;
use App\Data\PublicContent\Listing\FacetOptionData;
use App\Data\PublicContent\Listing\ListingFilterData;
use App\Enums\PublicContent\PublicDirectoryFacet;
use App\Framework\Database\QueryBuilder;

class PublicDirectoryFacetService
{
    public function __construct(
        private readonly PublicDirectoryFilterBuilder $filterBuilder,
    ) {
    }

    /**
     * @param QueryBuilder $baseQuery already scoped to site + status + the parent entity (author/category/tag),
     *                                 but WITHOUT sort/limit/offset applied.
     * @param list<string> $enabledFacets values of PublicDirectoryFacet enabled for this site
     * @return list<FacetGroupData>
     */
    public function build(QueryBuilder $baseQuery, ListingFilterData $filter, array $enabledFacets): array
    {
        $groups = [];

        foreach ($enabledFacets as $facetKey) {
            $facet = PublicDirectoryFacet::tryFrom($facetKey);

            if ($facet === null) {
                continue;
            }

            $groups[] = $this->buildGroup(clone $baseQuery, $filter, $facet);
        }

        return $groups;
    }

    private function buildGroup(QueryBuilder $query, ListingFilterData $filter, PublicDirectoryFacet $facet): FacetGroupData
    {
        // Apply every filter except this facet's own selection, so a user can
        // still see counts for options they haven't picked in this dimension yet.
        $this->filterBuilder->forPages($query, $filter->withoutFacet($facet->value));

        $rows = $this->aggregate($query, $facet)->get();

        $selected = $filter->facetValues($facet->value);

        $options = $rows
            ->map(static fn(object $row): FacetOptionData => new FacetOptionData(
                value: (string) $row->facet_value,
                label: (string) $row->facet_label,
                count: (int) $row->facet_count,
                selected: in_array((string) $row->facet_value, $selected, true),
            ))
            ->toArray();

        return new FacetGroupData($facet->value, $facet->label(), $options);
    }

    private function aggregate(QueryBuilder $query, PublicDirectoryFacet $facet): QueryBuilder
    {
        return match ($facet) {
            PublicDirectoryFacet::Category => $query
                ->join('page_categories', 'pages.id', '=', 'page_categories.page_id')
                ->join('categories', 'page_categories.category_id', '=', 'categories.id')
                ->selectRaw('categories.id as facet_value, categories.name as facet_label, COUNT(DISTINCT pages.id) as facet_count')
                ->groupBy('categories.id', 'categories.name')
                ->orderByRaw('facet_count desc'),
            PublicDirectoryFacet::Tag => $query
                ->join('page_tags', 'pages.id', '=', 'page_tags.page_id')
                ->join('tags', 'page_tags.tag_id', '=', 'tags.id')
                ->selectRaw('tags.id as facet_value, tags.name as facet_label, COUNT(DISTINCT pages.id) as facet_count')
                ->groupBy('tags.id', 'tags.name')
                ->orderByRaw('facet_count desc'),
            PublicDirectoryFacet::Author => $query
                ->join('page_authors', 'pages.id', '=', 'page_authors.page_id')
                ->join('authors', 'page_authors.author_id', '=', 'authors.id')
                ->selectRaw('authors.id as facet_value, authors.name as facet_label, COUNT(DISTINCT pages.id) as facet_count')
                ->groupBy('authors.id', 'authors.name')
                ->orderByRaw('facet_count desc'),
            PublicDirectoryFacet::Year => $query
                ->whereNotNull('published_at')
                ->selectRaw('YEAR(pages.published_at) as facet_value, YEAR(pages.published_at) as facet_label, COUNT(DISTINCT pages.id) as facet_count')
                ->groupByRaw('YEAR(pages.published_at)')
                ->orderByRaw('facet_value desc'),
            PublicDirectoryFacet::Month => $query
                ->whereNotNull('published_at')
                ->selectRaw('MONTH(pages.published_at) as facet_value, MONTH(pages.published_at) as facet_label, COUNT(DISTINCT pages.id) as facet_count')
                ->groupByRaw('MONTH(pages.published_at)')
                ->orderByRaw('facet_value asc'),
            PublicDirectoryFacet::ArticleType => $query
                ->whereNotNull('page_type')
                ->selectRaw('page_type as facet_value, page_type as facet_label, COUNT(DISTINCT pages.id) as facet_count')
                ->groupBy('page_type')
                ->orderByRaw('facet_count desc'),
        };
    }
}