<?php

namespace App\Actions\PublicContent;

use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicAuthorDirectoryRepository;
use App\Repositories\PublicContent\PublicCategoryDirectoryRepository;
use App\Repositories\PublicContent\PublicTagDirectoryRepository;
use App\Services\PublicContent\Directory\PublicDirectoryPresenter;
use InvalidArgumentException;

final class GetPublicDirectoryAction
{
    public function __construct(
        private readonly PublicAuthorDirectoryRepository $authors,
        private readonly PublicCategoryDirectoryRepository $categories,
        private readonly PublicTagDirectoryRepository $tags,
        private readonly PublicDirectoryPresenter $presenter,
    ) {
    }

    public function index(string $type, int $siteId): array
    {
        $siteSlug = SiteContext::slug();
        $entities = match ($type) {
            'author' => $this->authors->getActive($siteId),
            'category' => $this->categories->getActive($siteId),
            'tag' => $this->tags->getAll($siteId),
            default => throw new InvalidArgumentException('Unsupported directory type.'),
        };

        return [
            'type' => $type,
            'title' => ucfirst($this->plural($type)),
            'entities' => $this->presenter->entities($type, $entities, $siteSlug),
        ];
    }

    public function show(string $type, string $slug, int $siteId): ?array
    {
        $siteSlug = SiteContext::slug();
        $entity = match ($type) {
            'author' => $this->authors->findActiveBySlug($siteId, $slug),
            'category' => $this->categories->findActiveBySlug($siteId, $slug),
            'tag' => $this->tags->findBySlug($siteId, $slug),
            default => throw new InvalidArgumentException('Unsupported directory type.'),
        };

        if (!$entity) {
            return null;
        }

        $pages = match ($type) {
            'author' => $this->authors->getPublishedPages($siteId, (int)$entity->id),
            'category' => $this->categories->getPublishedPages($siteId, (int)$entity->id),
            'tag' => $this->tags->getPublishedPages($siteId, (int)$entity->id),
        };

        $related = $type === 'category'
            ? $this->presenter->entities(
                'category',
                $this->categories->getChildren($siteId, (int)$entity->id),
                $siteSlug,
            )
            : [];

        return [
            'type' => $type,
            'entity' => $this->presenter->entity($type, $entity, $siteSlug),
            'pages' => $this->presenter->pages($pages, $siteSlug),
            'related' => $related,
            'stats' => [
                'page_count' => $pages->count(),
                'related_count' => count($related),
            ],
        ];
    }

    private function plural(string $type): string
    {
        return match ($type) {
            'category' => 'categories',
            'author' => 'authors',
            'tag' => 'tags',
            default => $type,
        };
    }
}
