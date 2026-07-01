<?php
declare(strict_types=1);

namespace App\Actions\PublicContent;

use App\Data\PublicContent\Listing\ListingFilterData;
use App\Enums\PublicContent\PublicDirectoryFacet;
use App\Enums\PublicContent\PublicDirectoryPageSort;
use App\Enums\PublicContent\PublicDirectoryType;
use App\Factories\PublicContent\PublicDirectoryPageDataFactory;
use App\Models\Site;
use App\Repositories\PublicContent\PublicBuyingGuideDirectoryRepository;
use App\Repositories\PublicContent\PublicReviewDirectoryRepository;
use App\Services\PublicContent\Directory\Listing\PublicDirectoryFacetService;
use App\Services\PublicContent\Directory\Listing\PublicDirectoryFilterBuilder;
use App\Services\PublicContent\Directory\Listing\PublicDirectorySortService;
use App\Services\PublicContent\Directory\PublicDirectoryCardConfigProvider;
use App\Services\PublicContent\Directory\PublicDirectoryListingConfigProvider;
use App\Services\PublicContent\Directory\PublicDirectoryPresenter;
use InvalidArgumentException;

final class GetPublicContentTypeListingAction
{
    public function __construct(
        private readonly PublicBuyingGuideDirectoryRepository $buyingGuides,
        private readonly PublicReviewDirectoryRepository $reviews,
        private readonly PublicDirectoryPresenter $presenter,
        private readonly PublicDirectoryPageDataFactory $pageDataFactory,
        private readonly PublicDirectoryCardConfigProvider $cardConfig,
        private readonly PublicDirectoryListingConfigProvider $listingConfig,
        private readonly PublicDirectoryFilterBuilder $filterBuilder,
        private readonly PublicDirectorySortService $sortService,
        private readonly PublicDirectoryFacetService $facetService,
    ) {
    }

    /**
     * Lists published pages of a given page-type directory (buying guides,
     * reviews) with the same search/sort/per-page/facet behaviour as an
     * entity directory's show() page — but with no entity to look up.
     */
    public function list(PublicDirectoryType $type, int $siteId, Site $site, string $siteSlug, ListingFilterData $filter): array
    {
        if ($type->hasEntity()) {
            throw new InvalidArgumentException("PublicDirectoryType::{$type->name} has an entity and must use GetPublicDirectoryAction instead.");
        }

        $config = $this->listingConfig->forSite($site, $type);
        $repository = $this->repositoryFor($type);
        $sort = PublicDirectoryPageSort::tryFrom($filter->sort) ?? PublicDirectoryPageSort::Newest;

        $query = $repository->basePagesQuery($siteId);
        $this->filterBuilder->forPages($query, $filter);

        $enabledFacets = array_values(array_intersect(
            $config->pageFacets,
            array_map(static fn(PublicDirectoryFacet $facet): string => $facet->value, PublicDirectoryFacet::cases()),
        ));
        $facetGroups = $this->facetService->build(clone $query, $filter, $enabledFacets);

        $this->sortService->applyPageSort($query, $sort);
        $result = $repository->paginateFiltered($query, $filter->perPage, $filter->page);

        $pageData = $result['data']->map(fn(object $page) => $this->pageDataFactory->make($page));

        return [
            'type' => $type->value,
            'title' => $type->title(),
            'pages' => $this->presenter->pages($pageData, $siteSlug),
            'page_card' => $this->presenter->pageCardConfig($this->cardConfig->forSite($site)),
            'search' => ['query' => $filter->search ?? ''],
            'sort' => ['current' => $sort->value, 'options' => $config->pageSorts],
            'per_page' => ['current' => $filter->perPage, 'options' => $config->perPageOptions],
            'facets' => $this->presenter->facets($facetGroups),
            'pagination' => $this->presenter->pagination($result['pagination']),
        ];
    }

    private function repositoryFor(PublicDirectoryType $type): PublicBuyingGuideDirectoryRepository|PublicReviewDirectoryRepository
    {
        return match ($type) {
            PublicDirectoryType::BuyingGuide => $this->buyingGuides,
            PublicDirectoryType::Review => $this->reviews,
            default => throw new InvalidArgumentException("Unsupported content-type listing: {$type->value}"),
        };
    }
}