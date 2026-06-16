<?php

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicContentAction;
use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Repositories\PublicContent\PublicTerritoryRepository;
use App\Resources\PublicContent\PublicContentResource;

final class PublicContentController extends Controller
{
    public function __construct(
        private readonly GetPublicContentAction $getPublicContent,
        private readonly PublicTerritoryRepository $territories,
        private readonly PublicContentPageRepository $pages,
    ) {
        parent::__construct();
    }

    public function show(string $slug): JsonResponse
    {
        return $this->respond($slug);
    }

    public function showRegionalHomepage(string $regionSlug): JsonResponse
    {
        $siteId = SiteContext::getId();
        $territory = $this->territories->findActiveBySlug($siteId, $regionSlug);

        if (!$territory) {
            return $this->errorResponse('Region not found.', 404);
        }

        $homepage = $this->pages->findCompleteHomepageForTerritory(
            $siteId,
            (int) $territory->id,
            (string) $territory->slug,
        );

        if (!$homepage) {
            return $this->errorResponse('Regional homepage not found.', 404);
        }

        return $this->respond((string) $homepage->slug, $regionSlug);
    }

    public function showRegional(string $regionSlug, string $slug): JsonResponse
    {
        return $this->respond($slug, $regionSlug);
    }

    private function respond(string $slug, ?string $regionSlug = null): JsonResponse
    {
        $document = $this->getPublicContent->execute(
            SiteContext::getId(),
            $slug,
            MemberAuth::check() ? MemberAuth::getMember() : null,
            $regionSlug,
        );

        if (!$document) {
            return $this->errorResponse('Content not found.', 404);
        }

        return $this->resourceResponse([
            'data' => (new PublicContentResource($document))->toArray(),
            'meta' => [
                'schema_version' => $document->schemaVersion,
                'generated_at' => date(DATE_ATOM),
                'region' => $regionSlug,
            ],
        ]);
    }
}
