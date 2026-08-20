<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\DTO\PublicContent\Widgets\PublicContentPagePickerItem;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Resources\PublicContent\PageWidgetLayoutResource;
use App\Services\PublicContent\Widgets\PageWidgetOverrideService;
use App\Services\PublicContent\Widgets\WidgetLayoutDebugLog;
use InvalidArgumentException;

final class PublicContentPageWidgetController extends Controller
{
    public function __construct(
        private readonly PageWidgetOverrideService $pageWidgets,
    ) {
        parent::__construct();
    }

    public function index(int $pageId): JsonResponse
    {
        try {
            $overrides = $this->pageWidgets->listForPage(SiteContext::getId(), $pageId);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }

        return $this->resourceResponse((new PageWidgetLayoutResource($overrides))->toArray());
    }

    public function pages(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $limit = (int) $request->input('per_page', 20);

        $pages = $this->pageWidgets->searchPages(SiteContext::getId(), $query, $limit);

        return $this->resourceResponse([
            'pages' => array_map(
                static fn(PublicContentPagePickerItem $page): array => $page->toArray(),
                $pages,
            ),
        ]);
    }

    public function update(int $pageId, Request $request): JsonResponse
    {
        $payload = $request->input('widgets', []);
        WidgetLayoutDebugLog::write('http_put', [
            'page_id' => $pageId,
            'site_id' => SiteContext::getId(),
            'content_type' => $request->getHeader('Content-Type'),
            'raw_body' => $request->getContent(),
            'parsed_widgets' => $payload,
        ]);
        if (!is_array($payload)) {
            return $this->errorResponse('Widgets must be a list of overrides.', 422);
        }

        try {
            $overrides = $this->pageWidgets->syncForPage(
                SiteContext::getId(),
                $pageId,
                array_values($payload),
            );
        } catch (InvalidArgumentException $exception) {
            $status = $exception->getMessage() === 'Content not found.' ? 404 : 422;

            return $this->errorResponse($exception->getMessage(), $status);
        }

        return $this->resourceResponse((new PageWidgetLayoutResource($overrides))->toArray());
    }
}
