<?php

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicDirectoryAction;
use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;

final class PublicDirectoryController extends Controller
{
    public function __construct(private readonly GetPublicDirectoryAction $directories)
    {
        parent::__construct();
    }

    public function authors(): JsonResponse
    {
        return $this->index('author');
    }

    public function author(string $slug): JsonResponse
    {
        return $this->show('author', $slug);
    }

    public function categories(): JsonResponse
    {
        return $this->index('category');
    }

    public function category(string $slug): JsonResponse
    {
        return $this->show('category', $slug);
    }

    public function tags(): JsonResponse
    {
        return $this->index('tag');
    }

    public function tag(string $slug): JsonResponse
    {
        return $this->show('tag', $slug);
    }

    private function index(string $type): JsonResponse
    {
        return $this->resourceResponse([
            'data' => $this->directories->index($type, SiteContext::getId()),
        ]);
    }

    private function show(string $type, string $slug): JsonResponse
    {
        $document = $this->directories->show($type, $slug, SiteContext::getId());

        if (!$document) {
            return $this->errorResponse('Directory entity not found.', 404);
        }

        return $this->resourceResponse(['data' => $document]);
    }
}
