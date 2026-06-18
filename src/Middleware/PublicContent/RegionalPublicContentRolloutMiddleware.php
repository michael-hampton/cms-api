<?php

namespace App\Middleware\PublicContent;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Framework\Http\Request;
use App\Framework\Middleware\MiddlewareInterface;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Repositories\PublicContent\PublicTerritoryRepository;
use App\Services\PublicContent\PublicContentRollout;
use App\Services\PublicContent\RendererGeoResolver;
use Closure;

final class RegionalPublicContentRolloutMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PublicContentRollout $rollout,
        private readonly PublicTerritoryRepository $territories,
        private readonly PublicContentPageRepository $pages,
        private readonly RenderPublicContentPageAction $render,
        private readonly RendererGeoResolver $geoResolver,
    ) {
    }

    public function handle(Request $request, Closure|callable $next)
    {
        $regionSlug = (string)$request->route('regionSlug', '');
        $pageSlug = (string)$request->route('pageSlug', '');

        if ($regionSlug === '' || $pageSlug === '') {
            return $next($request);
        }

        $siteId = SiteContext::getId();
        $territory = $this->territories->findActiveBySlug($siteId, $regionSlug);

        if (!$territory) {
            return $next($request);
        }

        $page = $this->pages->findPublishedBySlugForTerritory(
            $siteId,
            $pageSlug,
            (int)$territory->id,
        );

        if (!$page || $page->custom_handler || !$this->rollout->enabledFor($page)) {
            return $next($request);
        }

        return $this->render->execute(
            page: $page,
            preview: false,
            territory: $territory,
            geo: $this->geoResolver->resolve($request),
        );
    }
}
