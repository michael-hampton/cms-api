<?php

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicContentAction;
use App\Actions\PublicContent\PublicContentAccessDenied;
use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Resources\PublicContent\PublicContentResource;

final class PublicContentController extends Controller
{
    public function __construct(private readonly GetPublicContentAction $getPublicContent)
    {
        parent::__construct();
    }

    public function show(string $slug): JsonResponse
    {
        return $this->respond($slug);
    }

    public function showRegional(string $regionSlug, string $slug): JsonResponse
    {
        return $this->respond($slug, $regionSlug);
    }

    private function respond(string $slug, ?string $regionSlug = null): JsonResponse
    {
        try {
            $document = $this->getPublicContent->execute(
                SiteContext::getId(),
                $slug,
                MemberAuth::check() ? MemberAuth::getMember() : null,
                $regionSlug,
            );
        } catch (PublicContentAccessDenied $exception) {
            return $this->errorResponse($exception->getMessage(), 403);
        }

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
