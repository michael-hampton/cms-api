<?php

namespace App\Actions\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\PublicContentDocument;
use App\DTO\PublicContent\ResolvedGeo;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Page;
use App\Models\Territory;
use App\Parsers\PageGridRenderer;
use App\Repositories\Cms\Pages\PageGridRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Repositories\PublicContent\PublicTerritoryRepository;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\PublicContent\Composition\PublicContentComposer;
use App\Services\PublicContent\Composition\PublicContentCompositionData;
use App\Services\PublicContent\CompositionDeadline;
use App\Services\PublicContent\Images\PublicContentImageUrlTransformer;
use App\Services\PublicContent\Inheritance\PublicContentEffectivePageResolver;
use App\Services\PublicContent\Islands\PublicContentIslandFiller;
use App\Services\PublicContent\Layout\PublicContentLayoutPrecedenceResolver;
use App\Services\PublicContent\Locale\PublicContentLocaleResolver;
use App\Services\PublicContent\Paywall\PublicContentPaywallModeResolver;
use App\Services\PublicContent\PublicContentRenderer;
use App\Services\PublicContent\Routing\PublicContentRouteOverrideResolver;
use App\Services\PublicContent\Routing\PublicContentSubscriberStatusResolver;
use App\Services\PublicContent\Slugs\PublicContentLinkRewriter;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;
use RuntimeException;

final class GetPublicContentAction
{
    public function __construct(
        private readonly PublicContentPageRepository $pages,
        private readonly PublicTerritoryRepository $territories,
        private readonly ArticleAccessService $access,
        private readonly PublicContentRenderer $renderer,
        private readonly PublicContentImageUrlTransformer $imageUrls,
        private readonly PublicContentCompositionData $compositionData,
        private readonly PublicContentComposer $composer,
        private readonly PageGridRepository $pageGrids,
        private readonly PublicContentPaywallModeResolver $paywallMode,
        private readonly PublicContentPathResolver $paths,
        private readonly PublicContentLinkRewriter $linkRewriter,
        private readonly PublicContentEffectivePageResolver $effectivePages,
        private readonly PublicContentLayoutPrecedenceResolver $layoutPrecedence,
        private readonly PublicContentIslandFiller $islandFiller,
        private readonly PublicContentRouteOverrideResolver $routeOverrides,
        private readonly PublicContentSubscriberStatusResolver $subscriberStatus,
        private readonly PublicContentLocaleResolver $localeResolver,
    ) {
    }

    public function execute(
        int $siteId,
        string $slug,
        ?Member $member = null,
        ?string $territorySlug = null,
        ?ResolvedGeo $geo = null,
        ?CompositionDeadline $deadline = null,
    ): ?PublicContentDocument {
        $territory = $territorySlug !== null
            ? $this->territories->findActiveBySlug($siteId, $territorySlug)
            : null;
        
        if ($territorySlug !== null && !$territory) {
            return null;
        }

        $page = $territory
            ? $this->pages->findCompletePublishedBySlugForTerritory($siteId, $slug, (int) $territory->id)
            : $this->pages->findCompletePublishedBySlug($siteId, $slug);

        if (!$page) {
            return null;
        }

        if ((int) $page->site_id !== $siteId) {
            throw new RuntimeException('Public content site scope mismatch.');
        }

        $effectivePage = $this->effectivePages->resolve($page);
        $layout = $this->layoutPrecedence->resolve($effectivePage);

        if (!$territory && !empty($page->territory_id)) {
            $territory = $this->territories->findActiveById(
                $siteId,
                (int) $page->territory_id,
            );
        }

        $siteSlug = SiteContext::slug();
        $base = '/api/v1/' . rawurlencode($siteSlug) . '/content/' . $page->id;
        $links = [
            'viewer_state' => $base . '/viewer-state',
            'comments' => $base . '/comments',
            'like' => $base . '/like',
            'view' => $base . '/views',
            'canonical' => $territory
                ? $this->regionalCanonicalUrl($siteSlug, $territory, $page)
                : sprintf('/%s/%s', rawurlencode($siteSlug), $this->encodePath($this->paths->canonicalPathForPage($page))),
        ];

        $decision = $this->access->canView($page, $member);
        $canView = (bool) ($decision['can_view'] ?? false);
        $access = [
            'can_view' => $canView,
            'reason' => $canView ? null : (string) ($decision['reason'] ?? 'subscription_required'),
        ];

        if (!$canView) {
            return $this->restrictedDocument(
                page: $page,
                siteId: $siteId,
                siteSlug: $siteSlug,
                member: $member,
                territory: $territory,
                links: $links,
                access: $access,
                geo: $geo,
                layout: $layout->toArray(),
            );
        }

        $viewData = $this->compositionData->build(
            page: $page,
            siteId: $siteId,
            siteSlug: $siteSlug,
            member: $member,
            links: $links,
            territory: $territory,
            deadline: $deadline,
        );
        $viewData['access'] = $access;
        $viewData['geo'] = $geo?->toArray();

        if ($territory) {
            $viewData = array_merge($viewData, $this->regionalViewData($page, $territory, $siteId, $siteSlug));
        }

        $components = $this->composer->compose(new PublicContentContext(
            page: $page,
            siteId: $siteId,
            siteSlug: $siteSlug,
            member: $member,
            viewData: $viewData,
        ));

        $territorySlug = $territory !== null ? (string) $territory->slug : null;
        $components = $this->linkRewriter->rewriteComponentLinks($components, $siteId, $siteSlug, $territorySlug);
        $components = $this->rewriteComponentImageUrls($components, $siteSlug);
        $rendered = $this->renderer->render($page, $siteId, $member);
        $regions = $this->fillIslandMarkers(
            $this->linkRewriter->rewriteContentRegions(
                $rendered['regions'],
                $siteId,
                $siteSlug,
                $territorySlug,
            ),
            $components,
        );

        return new PublicContentDocument(
            id: (int) $page->id,
            siteId: (int) $page->site_id,
            slug: (string) $page->slug,
            type: (string) $page->page_type,
            title: (string) $page->title,
            summary: $page->meta_description ?: null,
            seo: $page->seo ? $page->seo->toArray() : [],
            taxonomy: $this->taxonomy($page),
            regions: $regions,
            components: $components,
            authors: $this->authors($page),
            landingSections: [],
            links: $links,
            widgets: array_filter([
                'territory' => $territory ? $this->territoryData($territory) : null,
                'geo' => $geo?->toArray(),
                'layout' => $layout->toArray(),
                'routing' => $this->resolvedRouting($effectivePage->settings, $territory, $member, $siteId),
                'adverts' => $rendered['adverts'] ?? null,
                'deals' => isset($viewData['todaysDealsResult'])
                    ? [
                        'status' => $viewData['todaysDealsResult']->status->value,
                        'reason' => $viewData['todaysDealsResult']->reason,
                        'items' => $viewData['todaysDealsResult']->items(),
                    ]
                    : null,
            ], static fn(mixed $value): bool => $value !== null),
            access: $access,
            schemaVersion: '1.2',
        );
    }

    private function restrictedDocument(
        Page $page,
        int $siteId,
        string $siteSlug,
        ?Member $member,
        ?Territory $territory,
        array $links,
        array $access,
        ?ResolvedGeo $geo,
        array $layout,
    ): PublicContentDocument {
        $preview = trim((string) ($page->listing_synopsis ?: $page->meta_description ?: $page->description ?: ''));
        $previewHtml = $preview !== ''
            ? '<div class="premium-content-preview"><p>' . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') . '</p></div>'
            : '<div class="premium-content-preview" aria-hidden="true"></div>';

        $paywallMode = $this->paywallMode->resolve($page);
        $viewData = [
            'access' => $access,
            'links' => $links,
            'siteSlug' => $siteSlug,
            'territory' => $territory,
            'geo' => $geo?->toArray(),
            'paywallMode' => $paywallMode,
            'subscriptionModalData' => $paywallMode === PublicContentPaywallModeResolver::MODE_SUBSCRIPTION
                ? $this->compositionData->subscriptionModalData($member, $siteId)
                : null,
        ];

        $components = $this->composer->compose(new PublicContentContext(
            page: $page,
            siteId: $siteId,
            siteSlug: $siteSlug,
            member: $member,
            viewData: $viewData,
        ));
        $territorySlug = $territory !== null ? (string) $territory->slug : null;
        $components = $this->linkRewriter->rewriteComponentLinks($components, $siteId, $siteSlug, $territorySlug);
        $components = $this->rewriteComponentImageUrls($components, $siteSlug);
        $regions = $this->fillIslandMarkers(
            $this->linkRewriter->rewriteContentRegions(
                [new ContentRegion('main', [], $previewHtml)],
                $siteId,
                $siteSlug,
                $territorySlug,
            ),
            $components,
        );

        return new PublicContentDocument(
            id: (int) $page->id,
            siteId: (int) $page->site_id,
            slug: (string) $page->slug,
            type: (string) $page->page_type,
            title: (string) $page->title,
            summary: $page->meta_description ?: null,
            seo: $page->seo ? $page->seo->toArray() : [],
            taxonomy: $this->taxonomy($page),
            regions: $regions,
            components: $components,
            authors: $this->authors($page),
            landingSections: [],
            links: $links,
            widgets: array_filter([
                'territory' => $territory ? $this->territoryData($territory) : null,
                'geo' => $geo?->toArray(),
                'layout' => $layout,
            ], static fn(mixed $value): bool => $value !== null),
            access: $access,
        );
    }

    /**
     * Fill reserved island markers in region shells with composed fragments.
     * No-op when the shell has no markers. One missing/failed fragment never blanks the page.
     *
     * @param list<ContentRegion>|array<string, ContentRegion> $regions
     * @param array<string, list<PublicContentComponent>> $components
     * @return list<ContentRegion>|array<string, ContentRegion>
     */
    private function fillIslandMarkers(array $regions, array $components): array
    {
        $fragments = [];
        foreach ($components as $items) {
            foreach ($items as $component) {
                $fragments[$component->id] = $component->html;
                $fragments[$component->type] = $component->html;
            }
        }

        foreach ($regions as $key => $region) {
            if (!$region instanceof ContentRegion) {
                continue;
            }

            $filled = $this->islandFiller->fill($region->renderedHtml, $fragments);
            if ($filled === $region->renderedHtml) {
                continue;
            }

            $regions[$key] = new ContentRegion(
                name: $region->name,
                blocks: $region->blocks,
                renderedHtml: $filled,
            );
        }

        return $regions;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|null
     */
    private function resolvedRouting(array $settings, ?Territory $territory, ?Member $member, int $siteId): ?array
    {
        $routing = $settings['routing'] ?? null;
        if (!is_array($routing) || $routing === []) {
            return null;
        }

        $locale = $this->localeResolver->fromTerritory($territory);

        return $this->routeOverrides->resolve(
            $routing,
            $locale->language,
            $locale->region,
            $this->subscriberStatus->resolve($member, $siteId),
        );
    }

    private function regionalViewData(Page $page, Territory $territory, int $siteId, string $siteSlug): array
    {
        $grid = $this->pageGrids->getActiveGridForTerritory((int) $territory->id);
        $pageGridHtml = $grid ? (new PageGridRenderer(app(PublicContentPathResolver::class)))->render($grid, $territory) : null;

        return [
            'territory' => $territory,
            'allTerritories' => $this->territories->getActiveForSite($siteId),
            'pageGridHtml' => $pageGridHtml !== null
                ? $this->imageUrls->transformHtml($pageGridHtml, $siteSlug)
                : null,
            'regionArticles' => $this->pages->getRelatedForTerritory(
                $siteId,
                (int) $territory->id,
                (int) $page->id,
                6,
            ),
        ];
    }

    private function regionalCanonicalUrl(string $siteSlug, Territory $territory, Page $page): string
    {
        if ((string) $page->slug === (string) $territory->slug) {
            return sprintf(
                '/%s/%s',
                rawurlencode($siteSlug),
                rawurlencode((string) $territory->slug),
            );
        }

        return sprintf(
            '/%s/%s/%s',
            rawurlencode($siteSlug),
            rawurlencode((string) $territory->slug),
            $this->encodePath($this->paths->canonicalPathForPage($page)),
        );
    }

    private function encodePath(string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== '');

        return implode('/', array_map(rawurlencode(...), $segments));
    }

    private function taxonomy(Page $page): array
    {
        return [
            'categories' => $page->categories?->map(static fn($item) => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'slug' => (string) $item->slug,
            ])->toArray() ?? [],
            'tags' => $page->tags?->map(static fn($item) => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'slug' => (string) $item->slug,
            ])->toArray() ?? [],
        ];
    }

    private function authors(Page $page): array
    {
        return $page->authors?->map(static fn($author) => [
            'id' => (int) $author->id,
            'name' => (string) ($author->name ?? ''),
            'slug' => (string) ($author->slug ?? ''),
            'bio' => $author->bio ?? null,
            'image' => $author->avatar ?? null,
        ])->toArray() ?? [];
    }

    /**
     * @param array<string, list<PublicContentComponent>> $components
     * @return array<string, list<PublicContentComponent>>
     */
    private function rewriteComponentImageUrls(array $components, string $siteSlug): array
    {
        foreach ($components as $region => $items) {
            $components[$region] = array_map(
                function (PublicContentComponent $component) use ($siteSlug): PublicContentComponent {
                    $html = $this->imageUrls->transformHtml($component->html, $siteSlug);

                    if ($html === $component->html) {
                        return $component;
                    }

                    return new PublicContentComponent(
                        id: $component->id,
                        type: $component->type,
                        region: $component->region,
                        priority: $component->priority,
                        html: $html,
                        styles: $component->styles,
                        scripts: $component->scripts,
                        endpoints: $component->endpoints,
                        stateful: $component->stateful,
                        hydration: $component->hydration,
                    );
                },
                $items,
            );
        }

        return $components;
    }

    private function territoryData(Territory $territory): array
    {
        return [
            'id' => (int) $territory->id,
            'slug' => (string) $territory->slug,
            'name' => (string) $territory->name,
            'code' => $territory->code ?? null,
        ];
    }
}
