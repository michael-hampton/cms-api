<?php

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicDirectoryAction;
use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use InvalidArgumentException;

final class PublicDirectoryController extends Controller
{
    public function __construct(private readonly GetPublicDirectoryAction $directories)
    {
        parent::__construct();
    }

    public function index(string $type): JsonResponse
    {
        try {
            return $this->resourceResponse([
                'data' => $this->directories->index($type, SiteContext::getId()),
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    public function show(string $type, string $slug): JsonResponse
    {
        try {
            $document = $this->directories->show($type, $slug, SiteContext::getId());
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }

        if (!$document) {
            return $this->errorResponse('Directory entity not found.', 404);
        }

        return $this->resourceResponse(['data' => $document]);
    }
}
