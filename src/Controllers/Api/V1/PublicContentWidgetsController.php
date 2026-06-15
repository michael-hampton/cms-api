<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Services\PublicContent\PublicContentSupplementaryService;

final class PublicContentWidgetsController extends Controller
{
    public function __construct(private readonly PublicContentSupplementaryService $supplementary)
    {
        parent::__construct();
    }

    public function show(int $pageId): JsonResponse
    {
        $page = Page::with(['products'])
            ->where('id', $pageId)
            ->where('site_id', SiteContext::getId())
            ->where('status', 'published')
            ->first();

        if (!$page instanceof Page) {
            return $this->errorResponse('Content not found.', 404);
        }

        return $this->jsonResponse([
            'data' => $this->supplementary->for(
                $page,
                SiteContext::getId(),
                SiteContext::slug(),
            ),
        ]);
    }
}
