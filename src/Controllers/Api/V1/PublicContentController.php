<?php

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicContentByPathAction;
use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Resources\PublicContent\PublicContentResource;
use App\Services\PublicContent\Parity\PublicContentParityMonitor;
use App\Services\PublicContent\PublicContentResilience;
use App\Services\PublicContent\ResolvedGeoQueryParser;
use App\Services\Resilience\CircuitOpenException;
use App\Services\Resilience\OperationContext;
use App\Services\Resilience\OperationTimedOutException;
use InvalidArgumentException;

final class PublicContentController extends Controller
{
    public function __construct(
        private readonly GetPublicContentByPathAction $getPublicContent,
        private readonly PublicContentParityMonitor $parityMonitor,
        private readonly PublicContentResilience $resilience,
        private readonly ResolvedGeoQueryParser $geoParser,
    ) {
        parent::__construct();
    }

    public function show(string $contentPath, Request $request): JsonResponse
    {
        return $this->respond($contentPath, $request);
    }

    public function showRegional(string $regionSlug, string $contentPath, Request $request): JsonResponse
    {
        return $this->respond($contentPath, $request, $regionSlug);
    }

    private function respond(string $contentPath, Request $request, ?string $regionSlug = null): JsonResponse
    {
        $member = MemberAuth::check() ? MemberAuth::getMember() : null;

        try {
            $geo = $this->geoParser->parse($request);

            $document = $this->resilience->execute(
                function (OperationContext $context) use ($contentPath, $regionSlug, $member, $geo) {
                    $context->throwIfExpired();

                    $document = $this->getPublicContent->execute(
                        SiteContext::getId(),
                        $contentPath,
                        $member,
                        $regionSlug,
                        $geo,
                    );

                    $context->throwIfExpired();

                    return $document;
                },
            );

        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (OperationTimedOutException|CircuitOpenException) {
            return $this->errorResponse('service_unavailable', 503);
        }

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
                'geo' => $geo->toArray(),
            ],
        ]);
    }
}
