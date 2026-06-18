<?php

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicContentAction;
use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Resources\PublicContent\PublicContentResource;
use App\Services\PublicContent\Parity\PublicContentParityMonitor;

final class PublicContentController extends Controller
{
    public function __construct(
        private readonly GetPublicContentAction $getPublicContent,
        private readonly PublicContentParityMonitor $parityMonitor,
    ) {
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
        $member = MemberAuth::check() ? MemberAuth::getMember() : null;
        $document = $this->getPublicContent->execute(
            SiteContext::getId(),
            $slug,
            $member,
            $regionSlug,
        );

        if (!$document) {
            return $this->errorResponse('Content not found.', 404);
        }

        $this->parityMonitor->compareDocument($document, $member);

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
