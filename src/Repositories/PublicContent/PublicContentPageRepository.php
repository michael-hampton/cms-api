<?php

namespace App\Repositories\PublicContent;

use App\Models\Page;
use App\Models\Site;
use App\Repositories\Repository;

final class PublicContentPageRepository extends Repository
{
    private const COMPLETE_RELATIONS = [
        'blocks',
        'categories',
        'tags',
        'metadata',
        'seo',
        'settings',
        'social',
        'customFields',
        'customFields.customFieldDefinition',
        'authors',
        'pageAuthors',
        'pageAuthors.author',
        'regionSets',
        'territories',
        'products',
        'owner',
    ];

    public function findPublishedById(int $pageId, int $siteId, array $relations = []): ?Page
    {
        $query = $relations === [] ? Page::query() : Page::with($relations);

        $page = $query
            ->where('id', $pageId)
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->first();

        return $page instanceof Page ? $page : null;
    }

    public function findPublishedBySlug(
        int $siteId,
        string $slug,
        array $relations = [],
    ): ?Page {
        $query = $relations === [] ? Page::query() : Page::with($relations);

        $page = $query
            ->where('site_id', $siteId)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        return $page instanceof Page ? $page : null;
    }

    public function findCompletePublishedBySlug(int $siteId, string $slug): ?Page
    {
        return $this->findPublishedBySlug(
            $siteId,
            $slug,
            self::COMPLETE_RELATIONS,
        );
    }

    public function findHomepage(Site $site): ?Page
    {
        $pageId = $site->getSetting('homepage_page_id');
        if ($pageId) {
            $page = $this->findPublishedById((int)$pageId, (int)$site->id);
            if ($page) {
                return $page;
            }
        }

        $configuredSlug = $site->getSetting(
            'homepage_slug',
            $site->getSetting('homepage_page_slug'),
        );

        if ($configuredSlug) {
            $page = $this->findPublishedBySlug((int)$site->id, (string)$configuredSlug);
            if ($page) {
                return $page;
            }
        }

        $home = $this->findPublishedBySlug((int)$site->id, 'home');
        if ($home) {
            return $home;
        }

        $page = Page::where('site_id', (int)$site->id)
            ->where('status', 'published')
            ->where('page_type', 'landing-page')
            ->orderBy('created_at')
            ->first();

        return $page instanceof Page ? $page : null;
    }

    protected function getModelClass(): string
    {
        return Page::class;
    }
}
