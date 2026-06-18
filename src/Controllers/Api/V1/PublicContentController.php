<?php

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicContentAction;
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
        private readonly GetPublicContentAction $getPublicContent,
        private readonly PublicContentParityMonitor $parityMonitor,
        private readonly PublicContentResilience $resilience,
        private readonly ResolvedGeoQueryParser $geoParser,
    ) {
        parent::__construct();
    }

    public function show(string $slug, Request $request): JsonResponse
    {
        return $this->respond($slug, $request);
    }

    public function showRegional(string $regionSlug, string $slug, Request $request): JsonResponse
    {
        return $this->respond($slug, $request, $regionSlug);
    }

    private function respond(string $slug, Request $request, ?string $regionSlug = null): JsonResponse
    {
        $member = MemberAuth::check() ? MemberAuth::getMember() : null;

        try {
            $geo = $this->geoParser->parse($request);

            $document = $this->resilience->execute(
                function (OperationContext $context) use ($slug, $regionSlug, $member, $geo) {
                    $context->throwIfExpired();

                    $document = $this->getPublicContent->execute(
                        SiteContext::getId(),
                        $slug,
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
