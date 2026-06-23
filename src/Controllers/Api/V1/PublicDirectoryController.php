<?php

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicDirectoryAction;
use App\Controllers\Controller;
use App\Enums\PublicContent\PublicDirectoryType;
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
        return $this->index(PublicDirectoryType::Author);
    }

    public function author(string $slug): JsonResponse
    {
        return $this->show(PublicDirectoryType::Author, $slug);
    }

    public function categories(): JsonResponse
    {
        return $this->index(PublicDirectoryType::Category);
    }

    public function category(string $slug): JsonResponse
    {
        return $this->show(PublicDirectoryType::Category, $slug);
    }

    public function tags(): JsonResponse
    {
        return $this->index(PublicDirectoryType::Tag);
    }

    public function tag(string $slug): JsonResponse
    {
        return $this->show(PublicDirectoryType::Tag, $slug);
    }

    private function index(PublicDirectoryType $type): JsonResponse
    {
        return $this->resourceResponse([
            'data' => $this->directories->index($type, SiteContext::getId()),
        ]);
    }

    private function show(PublicDirectoryType $type, string $slug): JsonResponse
    {
        $document = $this->directories->show($type, $slug, SiteContext::getId());

        if (!$document) {
            return $this->errorResponse('Directory entity not found.', 404);
        }

        return $this->resourceResponse(['data' => $document]);
    }
}
