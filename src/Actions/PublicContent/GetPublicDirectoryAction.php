<?php
// app/Actions/PublicContent/GetPublicDirectoryAction.php
declare(strict_types=1);

namespace App\Actions\PublicContent;

use App\Data\PublicContent\Listing\ListingFilterData;
use App\Data\PublicContent\PublicDirectoryEntityData;
use App\Enums\PublicContent\PublicDirectoryFacet;
use App\Enums\PublicContent\PublicDirectoryIndexSort;
use App\Enums\PublicContent\PublicDirectoryPageSort;
use App\Enums\PublicContent\PublicDirectoryType;
use App\Factories\PublicContent\PublicDirectoryPageDataFactory;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicAuthorDirectoryRepository;
use App\Repositories\PublicContent\PublicCategoryDirectoryRepository;
use App\Repositories\PublicContent\PublicTagDirectoryRepository;
use App\Repositories\Repository;
use App\Services\PublicContent\Directory\PublicDirectoryCardConfigProvider;
use App\Services\PublicContent\Directory\PublicDirectoryListingConfigProvider;
use App\Services\PublicContent\Directory\Listing\PublicDirectoryFacetService;
use App\Services\PublicContent\Directory\Listing\PublicDirectoryFilterBuilder;
use App\Services\PublicContent\Directory\Listing\PublicDirectorySortService;
use App\Services\PublicContent\Directory\PublicDirectoryPresenter;

final class GetPublicDirectoryAction
{
    public function __construct(
        private readonly PublicAuthorDirectoryRepository $authors,
        private readonly PublicCategoryDirectoryRepository $categories,
        private readonly PublicTagDirectoryRepository $tags,
        private readonly PublicDirectoryPresenter $presenter,
        private readonly PublicDirectoryPageDataFactory $pageDataFactory,
        private readonly PublicDirectoryCardConfigProvider $cardConfig,
        private readonly PublicDirectoryListingConfigProvider $listingConfig,
        private readonly PublicDirectoryFilterBuilder $filterBuilder,
        private readonly PublicDirectorySortService $sortService,
        private readonly PublicDirectoryFacetService $facetService,
    ) {
    }

    public function index(PublicDirectoryType $directoryType, int $siteId, ListingFilterData $filter): array
    {
        $siteSlug = SiteContext::slug();
        $config = $this->listingConfig->forSite(SiteContext::get(), $directoryType);
        $sort = PublicDirectoryIndexSort::tryFrom($filter->sort) ?? PublicDirectoryIndexSort::NameAsc;
        $repository = $this->repositoryFor($directoryType);

        $query = $repository->baseIndexQuery($siteId);
        $this->filterBuilder->forIndex($query, $filter);
        $this->sortService->applyIndexSort($query, $sort);

        $result = $repository->paginateFiltered($query, $filter->perPage, $filter->page);

        $entityData = $result['data']->map(
            static fn(object $entity): PublicDirectoryEntityData => PublicDirectoryEntityData::fromEntity(
                $directoryType,
                $entity,
            ),
        );

        return [
            'type' => $directoryType->value,
            'title' => $directoryType->title(),
            'entities' => $this->presenter->entities($entityData, $siteSlug),
            'search' => ['query' => $filter->search ?? ''],
            'sort' => ['current' => $sort->value, 'options' => $config->indexSorts],
            'per_page' => ['current' => $filter->perPage, 'options' => $config->perPageOptions],
            'pagination' => $this->presenter->pagination($result['pagination']),
        ];
    }

    public function show(PublicDirectoryType $directoryType, string $slug, int $siteId, ListingFilterData $filter): ?array
    {
        $site = SiteContext::get();
        $siteSlug = SiteContext::slug();
        $config = $this->listingConfig->forSite($site, $directoryType);
        $repository = $this->repositoryFor($directoryType);

        $entity = match ($directoryType) {
            PublicDirectoryType::Author => $this->authors->findActiveBySlug($siteId, $slug),
            PublicDirectoryType::Category => $this->categories->findActiveBySlug($siteId, $slug),
            PublicDirectoryType::Tag => $this->tags->findForSiteBySlug($siteId, $slug),
            PublicDirectoryType::BuyingGuide, PublicDirectoryType::Review => throw new \InvalidArgumentException(
                "PublicDirectoryType::{$directoryType->name} has no entity and no show() page.",
            ),
        };

        if (!$entity) {
            return null;
        }

        $sort = PublicDirectoryPageSort::tryFrom($filter->sort) ?? PublicDirectoryPageSort::Newest;

        $query = $repository->basePagesQuery($siteId, (int) $entity->id);
        $this->filterBuilder->forPages($query, $filter);

        $enabledFacets = array_values(array_intersect(
            $config->pageFacets,
            array_map(static fn(PublicDirectoryFacet $facet): string => $facet->value, PublicDirectoryFacet::cases()),
        ));
        $facetGroups = $this->facetService->build(clone $query, $filter, $enabledFacets);

        $this->sortService->applyPageSort($query, $sort);
        $result = $repository->paginateFiltered($query, $filter->perPage, $filter->page);

        $pageData = $result['data']->map(fn(object $page) => $this->pageDataFactory->make($page));

        $related = $directoryType === PublicDirectoryType::Category
            ? $this->presenter->entities(
                $this->categories
                    ->getChildren($siteId, (int) $entity->id)
                    ->map(static fn(object $child): PublicDirectoryEntityData => PublicDirectoryEntityData::fromEntity(
                        PublicDirectoryType::Category,
                        $child,
                    )),
                $siteSlug,
            )
            : [];

        return [
            'type' => $directoryType->value,
            'entity' => $this->presenter->entity(
                PublicDirectoryEntityData::fromEntity($directoryType, $entity),
                $siteSlug,
            ),
            'pages' => $this->presenter->pages($pageData, $siteSlug),
            'page_card' => $this->presenter->pageCardConfig($this->cardConfig->forSite($site)),
            'related' => $related,
            'search' => ['query' => $filter->search ?? ''],
            'sort' => ['current' => $sort->value, 'options' => $config->pageSorts],
            'per_page' => ['current' => $filter->perPage, 'options' => $config->perPageOptions],
            'facets' => $this->presenter->facets($facetGroups),
            'pagination' => $this->presenter->pagination($result['pagination']),
            'stats' => [
                'page_count' => (int) $result['pagination']['total'],
                'related_count' => count($related),
            ],
        ];
    }

    private function repositoryFor(PublicDirectoryType $type): Repository
    {
        return match ($type) {
            PublicDirectoryType::Author => $this->authors,
            PublicDirectoryType::Category => $this->categories,
            PublicDirectoryType::Tag => $this->tags,
            PublicDirectoryType::BuyingGuide, PublicDirectoryType::Review => throw new \InvalidArgumentException(
                "PublicDirectoryType::{$type->name} has no entity repository; use GetPublicContentTypeListingAction instead.",
            ),
        };
    }
}