<?php

namespace App\Controllers\Front;

use App\Actions\PublicContent\GetEditorialPreviewContentAction;
use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Resources\PublicContent\PublicContentResource;
use App\Services\PublicContent\EditorialPreviewAuthorizationService;

final class EditorialPublicContentPreviewController extends Controller
{
    public function __construct(
        private readonly PublicContentPageRepository $pages,
        private readonly EditorialPreviewAuthorizationService $authorization,
        private readonly RenderPublicContentPageAction $render,
        private readonly GetEditorialPreviewContentAction $content,
    ) {
        parent::__construct();
    }

    public function show(int $pageId): Response
    {
        $page = $this->authorizedPage($pageId);
        if ($page instanceof Response) {
            return $page;
        }

        $apiUrl = sprintf(
            '/api/v1/%s/editorial-preview/%d',
            rawurlencode(SiteContext::slug()),
            $pageId,
        );

        return $this->render->execute($page, true, null, $apiUrl);
    }

    public function data(int $pageId): JsonResponse
    {
        $page = $this->authorizedPage($pageId);
        if ($page instanceof Response) {
            return $this->errorResponse($page->getStatusCode() === 401 ? 'Authentication required.' : 'Forbidden.', $page->getStatusCode());
        }

        $document = $this->content->execute(SiteContext::getId(), $pageId);
        if (!$document) {
            return $this->errorResponse('Content not found.', 404);
        }

        return $this->resourceResponse([
            'data' => (new PublicContentResource($document))->toArray(),
            'meta' => [
                'schema_version' => $document->schemaVersion,
                'generated_at' => date(DATE_ATOM),
                'editorial_preview' => true,
                'page_status' => (string) $page->status,
            ],
        ]);
    }

    private function authorizedPage(int $pageId): mixed
    {
        $user = User::hydrateStatic(Auth::getUser());

        if (!$user) {
            return Response::html('Authentication required.', 401);
        }

        $page = $this->pages->findCompletePreviewById($pageId, SiteContext::getId());
        if (!$page) {
            return Response::html('Content not found.', 404);
        }

        if (!$this->authorization->canPreview($user, $page, SiteContext::getId())) {
            return Response::html('Forbidden.', 403);
        }

        return $page;
    }
}
