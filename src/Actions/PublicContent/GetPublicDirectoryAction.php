<?php

declare(strict_types=1);

namespace App\Actions\PublicContent;

use App\Data\PublicContent\PublicDirectoryEntityData;
use App\Data\PublicContent\PublicDirectoryPageData;
use App\Data\PublicContent\PublicDirectoryRelationData;
use App\Enums\PublicContent\PublicDirectoryType;
use App\Factories\PublicContent\PublicDirectoryPageDataFactory;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicAuthorDirectoryRepository;
use App\Repositories\PublicContent\PublicCategoryDirectoryRepository;
use App\Repositories\PublicContent\PublicTagDirectoryRepository;
use App\Services\PublicContent\Directory\PublicDirectoryCardConfigProvider;
use App\Services\PublicContent\Directory\PublicDirectoryPresenter;
use InvalidArgumentException;

final class GetPublicDirectoryAction
{
    public function __construct(
        private readonly PublicAuthorDirectoryRepository $authors,
        private readonly PublicCategoryDirectoryRepository $categories,
        private readonly PublicTagDirectoryRepository $tags,
        private readonly PublicDirectoryPresenter $presenter,
        private readonly PublicDirectoryPageDataFactory $pageDataFactory,
        private readonly PublicDirectoryCardConfigProvider $cardConfig,
    ) {
    }

    public function index(string $type, int $siteId): array
    {
        $directoryType = $this->directoryType($type);
        $siteSlug = SiteContext::slug();
        $entities = match ($directoryType) {
            PublicDirectoryType::Author => $this->authors->getActive($siteId),
            PublicDirectoryType::Category => $this->categories->getActive($siteId),
            PublicDirectoryType::Tag => $this->tags->getAll($siteId),
        };

        $entityData = $entities->map(
            static fn(object $entity): PublicDirectoryEntityData => PublicDirectoryEntityData::fromEntity(
                $directoryType,
                $entity,
            ),
        );

        return [
            'type' => $directoryType->value,
            'title' => $directoryType->title(),
            'entities' => $this->presenter->entities($entityData, $siteSlug),
        ];
    }

    public function show(string $type, string $slug, int $siteId): ?array
    {
        $directoryType = $this->directoryType($type);
        $site = SiteContext::get();
        $siteSlug = SiteContext::slug();
        $entity = match ($directoryType) {
            PublicDirectoryType::Author => $this->authors->findActiveBySlug($siteId, $slug),
            PublicDirectoryType::Category => $this->categories->findActiveBySlug($siteId, $slug),
            PublicDirectoryType::Tag => $this->tags->findForSiteBySlug($siteId, $slug),
        };

        if (!$entity) {
            return null;
        }

        $pages = match ($directoryType) {
            PublicDirectoryType::Author => $this->authors->getPublishedPages($siteId, (int) $entity->id),
            PublicDirectoryType::Category => $this->categories->getPublishedPages($siteId, (int) $entity->id),
            PublicDirectoryType::Tag => $this->tags->getPublishedPages($siteId, (int) $entity->id),
        };

        $currentRelation = PublicDirectoryRelationData::fromEntity($entity);
        $pageData = $pages->map(
            fn(object $page): PublicDirectoryPageData => $this->withCurrentDirectoryRelation(
                $this->pageDataFactory->make($page),
                $directoryType,
                $currentRelation,
            ),
        );

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
            'stats' => [
                'page_count' => $pages->count(),
                'related_count' => count($related),
            ],
        ];
    }

    private function withCurrentDirectoryRelation(
        PublicDirectoryPageData $page,
        PublicDirectoryType $type,
        PublicDirectoryRelationData $relation,
    ): PublicDirectoryPageData {
        $categories = $page->categories;
        $tags = $page->tags;
        $authors = $page->authors;

        match ($type) {
            PublicDirectoryType::Category => $categories = $this->appendRelation($categories, $relation),
            PublicDirectoryType::Tag => $tags = $this->appendRelation($tags, $relation),
            PublicDirectoryType::Author => $authors = $this->appendRelation($authors, $relation),
        };

        return new PublicDirectoryPageData(
            id: $page->id,
            title: $page->title,
            slug: $page->slug,
            summary: $page->summary,
            image: $page->image,
            publishedAt: $page->publishedAt,
            categories: $categories,
            tags: $tags,
            authors: $authors,
        );
    }

    /**
     * @param list<PublicDirectoryRelationData> $relations
     * @return list<PublicDirectoryRelationData>
     */
    private function appendRelation(array $relations, PublicDirectoryRelationData $relation): array
    {
        foreach ($relations as $existing) {
            if ($existing->slug === $relation->slug) {
                return $relations;
            }
        }

        return [...$relations, $relation];
    }

    private function directoryType(string $type): PublicDirectoryType
    {
        return PublicDirectoryType::tryFrom($type)
            ?? throw new InvalidArgumentException('Unsupported directory type.');
    }
}
