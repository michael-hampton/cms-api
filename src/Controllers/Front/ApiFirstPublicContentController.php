<?php

namespace App\Controllers\Front;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Repositories\PublicContent\PublicTerritoryRepository;
use App\Services\PublicContent\RendererGeoResolver;

final class ApiFirstPublicContentController extends Controller
{
    public function __construct(
        private readonly RenderPublicContentPageAction $render,
        private readonly PublicTerritoryRepository $territories,
        private readonly RendererGeoResolver $geoResolver,
    ) {
        parent::__construct();
    }

    public function show(Page $page, Request $request): Response
    {
        $territory = $this->territories->findActiveBySlug(
            SiteContext::getId(),
            (string) $page->slug,
        );

        return $this->render->execute(
            page: $page,
            preview: false,
            territory: $territory,
            geo: $this->geoResolver->resolve($request),
        );
    }
}
